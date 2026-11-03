<?php

/*
 * Broken object-level authorisation (BOLA) tests.
 *
 * A signed-in attacker who is a full admin of their own organisations, with a
 * two-factor-verified session, accepted terms and a verified identity, tries every
 * org-scoped endpoint with the victim's real IDs. Each attempt must get 404
 * (indistinguishable from a made-up ID) and change nothing — including the victim's money.
 */

use App\Models\Booking;
use App\Models\Campaign;
use App\Models\Creative;
use App\Models\Organisation;
use App\Models\Screen;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Http\UploadedFile;

beforeEach(function () {
    $victim = User::factory()->withMfa()->create();
    $this->victimAdvertiser = Organisation::factory()->verified()->withMember($victim)->create();
    $this->victimOwner = Organisation::factory()->owner()->verified()->withMember($victim)->create();
    $this->victimScreen = Screen::factory()->create(['organisation_id' => $this->victimOwner]);
    $this->victimCampaign = Campaign::factory()->create(['organisation_id' => $this->victimAdvertiser]);
    $this->victimCreative = Creative::factory()->create(['organisation_id' => $this->victimAdvertiser]);
    $this->victimBooking = Booking::create([
        'organisation_id' => $this->victimAdvertiser->id, 'screen_id' => $this->victimScreen->id, 'creative_id' => $this->victimCreative->id,
        'slot_index' => 0, 'status' => 'scheduled', 'price' => 500, 'price_per_play' => 2, 'plays_total' => 250, 'start_hour' => 8, 'end_hour' => 10,
        'date_range' => Booking::dateRange(now()->addDay()->toImmutable(), now()->addDays(2)->toImmutable()),
    ]);
    fundWallet($this->victimAdvertiser, 1000);

    $this->attacker = verifyIdentity(acceptTerms(User::factory()->withMfa()->create()));
    $this->attackerOrg = Organisation::factory()->verified()->withMember($this->attacker)->create();
    Organisation::factory()->owner()->verified()->withMember($this->attacker)->create();
    fundWallet($this->attackerOrg, 5000);
    $this->attackerCreative = Creative::factory()->create(['organisation_id' => $this->attackerOrg]);
});

dataset('foreign endpoints', [
    'show org' => ['GET', '/orgs/{adv}'],
    'show wallet' => ['GET', '/orgs/{adv}/wallet'],
    'list wallet transactions' => ['GET', '/orgs/{adv}/wallet/transactions'],
    'top up wallet' => ['POST', '/orgs/{adv}/wallet/topup'],
    'list creatives' => ['GET', '/orgs/{adv}/creatives'],
    'upload creative' => ['POST', '/orgs/{adv}/creatives'],
    'show creative' => ['GET', '/orgs/{adv}/creatives/{creative}'],
    'delete creative' => ['DELETE', '/orgs/{adv}/creatives/{creative}'],
    'list bookings' => ['GET', '/orgs/{adv}/bookings'],
    'create booking' => ['POST', '/orgs/{adv}/bookings'],
    'show booking' => ['GET', '/orgs/{adv}/bookings/{booking}'],
    'cancel booking' => ['POST', '/orgs/{adv}/bookings/{booking}/cancel'],
    'list campaigns' => ['GET', '/orgs/{adv}/campaigns'],
    'create campaign' => ['POST', '/orgs/{adv}/campaigns'],
    'show campaign' => ['GET', '/orgs/{adv}/campaigns/{campaign}'],
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
        '{creative}' => test()->victimCreative->uuid,
        '{screen}' => test()->victimScreen->uuid,
    ]);
}

function validBody(string $method, string $path): array
{
    // Valid bodies, so the request gets past validation to the ownership check.
    return match (true) {
        str_ends_with($path, '/campaigns') => ['name' => 'X', 'start_date' => now()->addDay()->toDateString(), 'end_date' => now()->addDays(3)->toDateString()],
        // The attacker's own ad on the victim's org: still must not be able to book there.
        str_ends_with($path, '/bookings') => ['screen_id' => test()->victimScreen->uuid, 'creative_id' => test()->attackerCreative->uuid,
            'start_date' => now()->addDays(3)->toDateString(), 'end_date' => now()->addDays(4)->toDateString(), 'start_hour' => 12, 'end_hour' => 13],
        str_ends_with($path, '/topup') => ['amount' => 500, 'gateway' => 'esewa'],
        str_ends_with($path, '/creatives') && $method === 'POST' => ['name' => 'Hijack ad', 'category' => 'retail', 'resolution' => '1920x1080',
            'declaration' => true, 'file' => UploadedFile::fake()->image('ad.png', 1920, 1080)],
        str_ends_with($path, '/screens') => ['name' => 'X', 'lat' => 27.7, 'lng' => 85.3, 'width_px' => 1920, 'height_px' => 1080, 'orientation' => 'landscape', 'price_per_play' => 1],
        $method === 'PATCH' => ['name' => 'Hijacked', 'price_per_play' => 1],
        default => [],
    };
}

function victimState(): array
{
    return [
        Campaign::count(), Booking::count(), Screen::count(), Creative::count(), WalletTransaction::count(),
        test()->victimScreen->fresh()->name, test()->victimBooking->fresh()->status,
        Wallet::where('organisation_id', test()->victimAdvertiser->id)->first()->only(['balance_paisa', 'reserved_paisa']),
    ];
}

it('returns 404 for another organisation\'s resources', function (string $method, string $path) {
    $before = victimState();

    actingAsApi($this->attacker, mfa: true)
        ->json($method, foreignUrl($path), validBody($method, $path))
        ->assertNotFound()
        ->assertJsonPath('message', 'Not found.');

    expect(victimState())->toEqual($before);
})->with('foreign endpoints');

it('will not book another organisation\'s ad, even onto the attacker\'s own organisation', function () {
    actingAsApi($this->attacker)->postJson("/api/v1/orgs/{$this->attackerOrg->uuid}/bookings", [
        'screen_id' => $this->victimScreen->uuid, 'creative_id' => $this->victimCreative->uuid,
        'start_date' => now()->addDays(3)->toDateString(), 'end_date' => now()->addDays(3)->toDateString(), 'start_hour' => 12, 'end_hour' => 13,
    ])->assertNotFound();
});

it('answers a foreign ID exactly like a made-up ID', function () {
    $made_up = '/api/v1/orgs/'.Str::uuid().'/bookings';
    $foreign = "/api/v1/orgs/{$this->victimAdvertiser->uuid}/bookings";

    $a = actingAsApi($this->attacker)->getJson($made_up);
    $b = actingAsApi($this->attacker)->getJson($foreign);

    expect($b->status())->toBe($a->status())->and($b->json())->toEqual($a->json());
});

it('does not let a booking from one org be reached through another org the user belongs to', function () {
    // The victim is a member of both orgs; the booking belongs to the advertiser org only.
    $victim = $this->victimAdvertiser->members()->first();
    $other = Organisation::factory()->verified()->withMember($victim)->create();

    actingAsApi($victim)->getJson("/api/v1/orgs/{$other->uuid}/bookings/{$this->victimBooking->uuid}")->assertNotFound();
    actingAsApi($victim)->getJson("/api/v1/orgs/{$other->uuid}/creatives/{$this->victimCreative->uuid}")->assertNotFound();
});

it('treats an org of the wrong type as not found', function () {
    $user = $this->victimAdvertiser->members()->first();

    actingAsApi($user, mfa: true)->getJson("/api/v1/orgs/{$this->victimAdvertiser->uuid}/screens")->assertNotFound();
    actingAsApi($user)->getJson("/api/v1/orgs/{$this->victimOwner->uuid}/bookings")->assertNotFound();
    actingAsApi($user)->getJson("/api/v1/orgs/{$this->victimOwner->uuid}/wallet")->assertNotFound();
});

it('lets viewers read but not spend or change anything', function () {
    $viewer = acceptTerms(User::factory()->create());
    $this->victimAdvertiser->members()->attach($viewer, ['role' => 'viewer']);
    $url = "/api/v1/orgs/{$this->victimAdvertiser->uuid}";

    actingAsApi($viewer)->getJson("{$url}/bookings")->assertOk();
    actingAsApi($viewer)->getJson("{$url}/wallet")->assertOk();
    actingAsApi($viewer)->postJson("{$url}/wallet/topup", ['amount' => 500, 'gateway' => 'esewa'])->assertForbidden();
    actingAsApi($viewer)->postJson("{$url}/bookings/{$this->victimBooking->uuid}/cancel")->assertForbidden();
    actingAsApi($viewer)->deleteJson("{$url}/creatives/{$this->victimCreative->uuid}")->assertForbidden();
});

it('locks a suspended organisation', function () {
    $user = $this->victimAdvertiser->members()->first();
    $this->victimAdvertiser->forceFill(['status' => 'suspended'])->save();

    actingAsApi($user)->getJson("/api/v1/orgs/{$this->victimAdvertiser->uuid}/bookings")->assertForbidden();
});

it('only lists the caller\'s own organisations', function () {
    $ids = collect(actingAsApi($this->attacker)->getJson('/api/v1/orgs')->assertOk()->json('data'))->pluck('id');

    expect($ids)->toHaveCount(2)
        ->not->toContain($this->victimAdvertiser->uuid)
        ->not->toContain($this->victimOwner->uuid);
});

it('rejects non-UUID ids at the router', function () {
    actingAsApi($this->attacker)->getJson('/api/v1/orgs/1/bookings')->assertNotFound();
    actingAsApi($this->attacker)->getJson("/api/v1/orgs/1' OR '1'='1/bookings")->assertNotFound();
});
