<?php

use App\Models\Booking;
use App\Models\Creative;
use App\Models\Organisation;
use App\Models\Screen;
use App\Models\User;
use App\Models\Wallet;
use App\Services\BookingService;
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

it('waits for the owner: one click approves the ad and it plays; reject refunds everything', function () {
    $adv = verifyIdentity(acceptTerms(User::factory()->create(['name' => 'Ram Thapa'])));
    $advOrg = Organisation::factory()->verified()->withMember($adv)->create(['name' => 'Ram Momo']);
    fundWallet($advOrg, 5000);
    $ad = Creative::factory()->inReview()->create(['organisation_id' => $advOrg, 'uploaded_by' => $adv]);
    $book = fn (string $from, string $to) => actingAsApi($adv)->postJson("/api/v1/orgs/{$advOrg->uuid}/bookings", [
        'screen_id' => $this->screen->uuid, 'creative_id' => $ad->uuid,
        'start_date' => $this->day, 'end_date' => $this->day, 'start_time' => $from, 'end_time' => $to,
    ])->assertCreated()->assertJsonPath('data.status', 'awaiting_owner')->json('data.id');

    $first = $book('12:00', '13:00');
    $second = $book('14:00', '15:00');

    // The owner sees who booked, when, and for which time.
    $pending = actingAsApi($this->owner)->getJson("{$this->base}/schedule?date={$this->day}")->json('data.pending');
    expect($pending)->toHaveCount(2)
        ->and($pending[0]['booked_by'])->toBe('Ram Thapa')
        ->and($pending[0]['advertiser'])->toBe('Ram Momo')
        ->and($pending[0]['start_time'])->toBe('12:00')
        ->and($pending[0]['booked_at'])->not->toBeNull();

    // Not playable until approved.
    expect(Booking::where('uuid', $first)->first()->status->value)->toBe('awaiting_owner');

    actingAsApi($this->owner, mfa: true)->postJson("{$this->base}/bookings/{$first}/approve", ['date' => $this->day])
        ->assertOk()->assertJsonPath('message', 'Approved. It plays at its booked times.')
        ->assertJsonCount(1, 'data.pending');
    expect(Booking::where('uuid', $first)->first()->status->value)->toBe('scheduled')
        ->and($ad->fresh()->status->value)->toBe('approved'); // the owner reviewed the ad too

    $balance = fn () => Wallet::where('organisation_id', $advOrg->id)->value('balance_paisa');
    $before = $balance();
    actingAsApi($this->owner, mfa: true)->postJson("{$this->base}/bookings/{$second}/reject", ['note' => 'Not for this location'])
        ->assertOk()->assertJsonCount(0, 'data.pending');
    expect(Booking::where('uuid', $second)->first()->status->value)->toBe('rejected')
        ->and($balance())->toBeGreaterThan($before); // refunded

    actingAsApi($this->owner, mfa: true)->postJson("{$this->base}/bookings/{$first}/approve")
        ->assertConflict()->assertJsonPath('message', 'This booking is not waiting for approval.');
});

it('refunds a booking the owner never approved once its time has passed', function () {
    $adv = verifyIdentity(acceptTerms(User::factory()->create()));
    $advOrg = Organisation::factory()->verified()->withMember($adv)->create();
    fundWallet($advOrg, 5000);
    $ad = Creative::factory()->create(['organisation_id' => $advOrg]);
    $id = actingAsApi($adv)->postJson("/api/v1/orgs/{$advOrg->uuid}/bookings", [
        'screen_id' => $this->screen->uuid, 'creative_id' => $ad->uuid,
        'start_date' => $this->day, 'end_date' => $this->day, 'start_time' => '12:00', 'end_time' => '13:00',
    ])->assertCreated()->json('data.id');

    app(BookingService::class)->advance(CarbonImmutable::parse($this->day.' 23:00', 'Asia/Kathmandu'));

    expect(Booking::where('uuid', $id)->first()->status->value)->toBe('rejected')
        ->and(Wallet::where('organisation_id', $advOrg->id)->value('balance_paisa'))->toBe(500000);
});
