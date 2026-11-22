<?php

use App\Models\Creative;
use App\Models\Organisation;
use App\Models\Screen;
use App\Models\User;
use App\Services\LoopService;
use Carbon\CarbonImmutable;
use Database\Seeders\RentallSeeder;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->ownerOrg = Organisation::factory()->owner()->verified()->withMember($this->owner)->create();
    $this->screen = Screen::factory()->create(['organisation_id' => $this->ownerOrg]); // 12 slots, 06:00–22:00
    $adOrg = Organisation::factory()->create(['name' => 'Momo House']);
    $this->ads = Creative::factory()->count(3)->create(['organisation_id' => $adOrg]);
    $this->day = now('Asia/Kathmandu')->addDays(2)->toDateString();
    $this->bookings = $this->ads->map(fn ($ad) => playableBooking($this->screen, $ad, $this->day, $this->day, 8, 10));
    $this->base = "/api/v1/orgs/{$this->ownerOrg->uuid}/screens/{$this->screen->uuid}";
});

it('shows the owner every booked slot of a day, hour by hour', function () {
    $res = actingAsApi($this->owner)->getJson("{$this->base}/schedule?date={$this->day}")->assertOk();

    expect($res->json('data.slots_per_loop'))->toBe(12)
        ->and($res->json('data.bookings'))->toHaveCount(3)
        ->and($res->json('data.bookings.0.advertiser'))->toBe('Momo House');
    $eight = collect($res->json('data.hours'))->firstWhere('hour', 8);
    expect($eight['used'])->toBe(3)
        ->and(array_column($eight['ads'], 'slots'))->toBe([[0], [1], [2]])
        ->and(collect($res->json('data.hours'))->firstWhere('hour', 12)['ads'])->toBe([]);
});

it('lets the owner move an ad earlier in the loop, and the screen plays the new order', function () {
    [$a, $b, $c] = $this->bookings;
    actingAsApi($this->owner, mfa: true)->postJson("{$this->base}/bookings/{$c->uuid}/move", ['direction' => 'up', 'date' => $this->day, 'hour' => 8])
        ->assertOk()->assertJsonPath('message', 'Play order changed.');

    $places = app(LoopService::class)->positions($this->screen->fresh(), CarbonImmutable::parse($this->day), 8);
    expect(array_keys($places))->toBe([$a->id, $c->id, $b->id])
        ->and(array_values($places))->toBe([[0], [1], [2]]);

    actingAsApi($this->owner, mfa: true)->postJson("{$this->base}/bookings/{$a->uuid}/move", ['direction' => 'up', 'date' => $this->day, 'hour' => 8])
        ->assertUnprocessable()->assertJsonPath('message', 'This ad already plays first.');
    actingAsApi($this->owner, mfa: true)->postJson("{$this->base}/bookings/{$a->uuid}/move", ['direction' => 'up', 'date' => $this->day, 'hour' => 15])
        ->assertUnprocessable()->assertJsonPath('message', 'This ad does not play in that hour.');
});

it('gives the owner a dashboard of screens, players and bookings', function () {
    actingAsApi($this->owner)->getJson("/api/v1/orgs/{$this->ownerOrg->uuid}/owner/overview")->assertOk()
        ->assertJsonPath('data.screens', 1)
        ->assertJsonPath('data.players', 0)
        ->assertJsonPath('data.upcoming', 3);
});

it('keeps two-factor for owner changes unless the lab turns it off', function () {
    $c = $this->bookings[2];
    actingAsApi($this->owner)->postJson("{$this->base}/bookings/{$c->uuid}/move", ['direction' => 'up', 'date' => $this->day, 'hour' => 8])
        ->assertForbidden();
    config(['dooh.owner_requires_mfa' => false]);
    actingAsApi($this->owner)->postJson("{$this->base}/bookings/{$c->uuid}/move", ['direction' => 'up', 'date' => $this->day, 'hour' => 8])
        ->assertOk();
});

it('seeds RentALL as an owner of several billboards that signs in with email and can also book', function () {
    $this->seed(RentallSeeder::class);
    $this->seed(RentallSeeder::class); // safe to run twice

    $login = $this->postJson('/api/v1/auth/login', ['email' => 'rentalnepal@screen.com', 'password' => 'rentalnepal@screen.com'])->assertOk();
    $user = User::where('email', 'rentalnepal@screen.com')->sole();
    $orgs = collect(actingAsApi($user)->getJson('/api/v1/orgs')->json('data'));
    expect($orgs->pluck('type')->sort()->values()->all())->toBe(['advertiser', 'owner'])
        ->and($login->json('data.token'))->not->toBeEmpty();

    $owner = $orgs->firstWhere('type', 'owner')['id'];
    actingAsApi($user)->getJson("/api/v1/orgs/{$owner}/owner/overview")->assertOk()->assertJsonPath('data.screens', 2);
    expect(collect(actingAsApi($user)->getJson("/api/v1/orgs/{$owner}/screens")->json('data'))->pluck('name')->all())
        ->toContain('RentALL Office Screen (Raspberry Pi)', 'RentALL Showroom Billboard');
    // Both are on the public map.
    expect(collect($this->getJson('/api/v1/screens')->json('data'))->pluck('name')->all())
        ->toContain('RentALL Office Screen (Raspberry Pi)', 'RentALL Showroom Billboard');
});
