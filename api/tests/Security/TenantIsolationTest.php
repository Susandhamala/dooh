<?php

/*
 * Broken object-level authorisation (BOLA) tests.
 *
 * A signed-in attacker who is a full admin of their own organisations, with a
 * two-factor-verified session, tries every org-scoped endpoint with the victim's
 * real IDs. Each attempt must get 404 (indistinguishable from a made-up ID)
 * and change nothing.
 */

use App\Models\Booking;
use App\Models\Campaign;
use App\Models\Organisation;
use App\Models\Screen;
use App\Models\User;

beforeEach(function () {
    $victim = User::factory()->withMfa()->create();
    $this->victimAdvertiser = Organisation::factory()->verified()->withMember($victim)->create();
    $this->victimOwner = Organisation::factory()->owner()->verified()->withMember($victim)->create();
    $this->victimScreen = Screen::factory()->create(['organisation_id' => $this->victimOwner]);
    $this->victimCampaign = Campaign::factory()->create(['organisation_id' => $this->victimAdvertiser]);
    $this->victimBooking = Booking::create([
        'campaign_id' => $this->victimCampaign->id, 'screen_id' => $this->victimScreen->id, 'slot_index' => 0,
        'status' => 'draft', 'price' => 500, 'date_range' => Booking::dateRange(now()->addDay()->toImmutable(), now()->addDays(2)->toImmutable()),
    ]);

    $this->attacker = User::factory()->withMfa()->create();
    Organisation::factory()->verified()->withMember($this->attacker)->create();
    Organisation::factory()->owner()->verified()->withMember($this->attacker)->create();
});

dataset('foreign endpoints', [
    'show org' => ['GET', '/orgs/{adv}'],
    'list campaigns' => ['GET', '/orgs/{adv}/campaigns'],
    'create campaign' => ['POST', '/orgs/{adv}/campaigns'],
    'show campaign' => ['GET', '/orgs/{adv}/campaigns/{campaign}'],
    'list bookings' => ['GET', '/orgs/{adv}/campaigns/{campaign}/bookings'],
    'create booking' => ['POST', '/orgs/{adv}/campaigns/{campaign}/bookings'],
    'cancel booking' => ['POST', '/orgs/{adv}/campaigns/{campaign}/bookings/{booking}/cancel'],
    'list owner screens' => ['GET', '/orgs/{own}/screens'],
    'show owner screen' => ['GET', '/orgs/{own}/screens/{screen}'],
    'create screen' => ['POST', '/orgs/{own}/screens'],
    'update screen' => ['PATCH', '/orgs/{own}/screens/{screen}'],
]);

function foreignUrl(string $path): string
{
    return '/api/v1'.strtr($path, [
        '{adv}' => test()->victimAdvertiser->uuid,
        '{own}' => test()->victimOwner->uuid,
        '{campaign}' => test()->victimCampaign->uuid,
        '{booking}' => test()->victimBooking->uuid,
        '{screen}' => test()->victimScreen->uuid,
    ]);
}

function validBody(string $method, string $path): array
{
    return match (true) {
        str_ends_with($path, '/campaigns') => ['name' => 'X', 'start_date' => now()->addDay()->toDateString(), 'end_date' => now()->addDays(3)->toDateString()],
        str_ends_with($path, '/bookings') => ['screen_id' => test()->victimScreen->uuid, 'start_date' => now()->addDays(3)->toDateString(), 'end_date' => now()->addDays(4)->toDateString()],
        str_ends_with($path, '/screens') => ['name' => 'X', 'lat' => 27.7, 'lng' => 85.3, 'width_px' => 1920, 'height_px' => 1080, 'orientation' => 'landscape', 'slot_price' => 1],
        $method === 'PATCH' => ['name' => 'Hijacked', 'slot_price' => 1],
        default => [],
    };
}

it('returns 404 for another organisation\'s resources', function (string $method, string $path) {
    $before = [Campaign::count(), Booking::count(), Screen::count(), $this->victimScreen->fresh()->name, $this->victimBooking->fresh()->status];

    actingAsApi($this->attacker, mfa: true)
        ->json($method, foreignUrl($path), validBody($method, $path))
        ->assertNotFound()
        ->assertJsonPath('message', 'Not found.');

    expect([Campaign::count(), Booking::count(), Screen::count(), $this->victimScreen->fresh()->name, $this->victimBooking->fresh()->status])
        ->toEqual($before);
})->with('foreign endpoints');

it('answers a foreign ID exactly like a made-up ID', function () {
    $made_up = '/api/v1/orgs/'.Str::uuid().'/campaigns';
    $foreign = "/api/v1/orgs/{$this->victimAdvertiser->uuid}/campaigns";

    $a = actingAsApi($this->attacker)->getJson($made_up);
    $b = actingAsApi($this->attacker)->getJson($foreign);

    expect($b->status())->toBe($a->status())->and($b->json())->toEqual($a->json());
});

it('does not let a campaign from one org be reached through another org the user belongs to', function () {
    // The victim is a member of both orgs; the campaign belongs to the advertiser org only.
    $victim = $this->victimAdvertiser->members()->first();
    $other = Organisation::factory()->verified()->withMember($victim)->create();

    actingAsApi($victim)->getJson("/api/v1/orgs/{$other->uuid}/campaigns/{$this->victimCampaign->uuid}")->assertNotFound();
});

it('treats an org of the wrong type as not found', function () {
    $user = $this->victimAdvertiser->members()->first();

    actingAsApi($user, mfa: true)->getJson("/api/v1/orgs/{$this->victimAdvertiser->uuid}/screens")->assertNotFound();
    actingAsApi($user)->getJson("/api/v1/orgs/{$this->victimOwner->uuid}/campaigns")->assertNotFound();
});

it('lets viewers read but not write', function () {
    $viewer = User::factory()->create();
    $this->victimAdvertiser->members()->attach($viewer, ['role' => 'viewer']);
    $url = "/api/v1/orgs/{$this->victimAdvertiser->uuid}/campaigns";

    actingAsApi($viewer)->getJson($url)->assertOk();
    actingAsApi($viewer)->postJson($url, validBody('POST', '/campaigns'))->assertForbidden();
    actingAsApi($viewer)->postJson("{$url}/{$this->victimCampaign->uuid}/bookings/{$this->victimBooking->uuid}/cancel")->assertForbidden();
});

it('locks a suspended organisation', function () {
    $user = $this->victimAdvertiser->members()->first();
    $this->victimAdvertiser->forceFill(['status' => 'suspended'])->save();

    actingAsApi($user)->getJson("/api/v1/orgs/{$this->victimAdvertiser->uuid}/campaigns")->assertForbidden();
});

it('only lists the caller\'s own organisations', function () {
    $ids = collect(actingAsApi($this->attacker)->getJson('/api/v1/orgs')->assertOk()->json('data'))->pluck('id');

    expect($ids)->toHaveCount(2)
        ->not->toContain($this->victimAdvertiser->uuid)
        ->not->toContain($this->victimOwner->uuid);
});

it('rejects non-UUID ids at the router', function () {
    actingAsApi($this->attacker)->getJson('/api/v1/orgs/1/campaigns')->assertNotFound();
    actingAsApi($this->attacker)->getJson("/api/v1/orgs/1' OR '1'='1/campaigns")->assertNotFound();
});
