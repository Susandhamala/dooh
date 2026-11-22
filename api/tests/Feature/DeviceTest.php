<?php

use App\Models\Creative;
use App\Models\Device;
use App\Models\Organisation;
use App\Models\Screen;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->publicKey = useTestDeviceKeys();
    $this->owner = User::factory()->withMfa()->create();
    $this->org = Organisation::factory()->owner()->verified()->withMember($this->owner)->create();
    $this->screen = Screen::factory()->create(['organisation_id' => $this->org]);
    $this->base = "/api/v1/orgs/{$this->org->uuid}/screens/{$this->screen->uuid}/device";
});

/** Decode and check a manifest envelope the way the player does. */
function openManifest(array $envelope, string $publicKey): array
{
    $bytes = base64_decode($envelope['payload'], true);
    expect(sodium_crypto_sign_verify_detached(base64_decode($envelope['signature']), $bytes, base64_decode($publicKey)))->toBeTrue();

    return json_decode($bytes, true);
}

it('enrols a device with a one-time code and hands back what the player needs', function () {
    actingAsApi($this->owner)->postJson("{$this->base}/code")->assertForbidden(); // two-factor first

    $res = actingAsApi($this->owner, mfa: true)->postJson("{$this->base}/code")->assertCreated();
    $code = $res->json('data.code');
    expect($code)->toMatch('/^[A-HJ-NP-Z2-9]{4}-[A-HJ-NP-Z2-9]{4}$/');

    resetAuth();
    $csr = file_get_contents(base_path('tests/Fixtures/devices/device-1.csr'));
    // Codes are forgiving about case and the dash.
    $data = $this->postJson('/device/v1/enrol', ['code' => strtolower(str_replace('-', ' ', $code)), 'csr' => $csr])
        ->assertCreated()->json('data');

    expect($data['screen_id'])->toBe($this->screen->uuid)
        ->and($data['signing_public_key'])->toBe($this->publicKey)
        ->and($data['ca_certificate'])->toBe(file_get_contents(base_path('tests/Fixtures/devices/ca.crt')));

    // The certificate is for the key in the CSR, signed by the device CA, for client auth only.
    $cert = openssl_x509_read($data['certificate']);
    expect(openssl_x509_verify($cert, openssl_pkey_get_public($data['ca_certificate'])))->toBe(1)
        ->and(openssl_pkey_get_details(openssl_pkey_get_public($cert))['key'])->toBe(openssl_pkey_get_details(openssl_csr_get_public_key($csr))['key'])
        ->and(openssl_x509_parse($cert)['extensions']['extendedKeyUsage'])->toBe('TLS Web Client Authentication');

    $this->postJson('/device/v1/enrol', ['code' => $code, 'csr' => $csr])->assertUnprocessable(); // spent

    actingAsApi($this->owner)->getJson($this->base)->assertOk()
        ->assertJsonPath('data.id', $data['device_id'])
        ->assertJsonPath('data.online', false);
});

it('refuses wrong, expired and replaced codes, and keys that are not Ed25519', function () {
    $csr = file_get_contents(base_path('tests/Fixtures/devices/device-1.csr'));
    $this->postJson('/device/v1/enrol', ['code' => 'ABCD-EFGH', 'csr' => $csr])
        ->assertUnprocessable()->assertJsonValidationErrors('code');

    $first = actingAsApi($this->owner, mfa: true)->postJson("{$this->base}/code")->json('data.code');
    $second = actingAsApi($this->owner, mfa: true)->postJson("{$this->base}/code")->json('data.code');
    resetAuth();
    $this->postJson('/device/v1/enrol', ['code' => $first, 'csr' => $csr])->assertUnprocessable();

    // A bad request doesn't spend the code.
    $this->postJson('/device/v1/enrol', ['code' => $second, 'csr' => file_get_contents(base_path('tests/Fixtures/devices/rsa.csr'))])
        ->assertUnprocessable()->assertJsonValidationErrors('csr');
    $this->postJson('/device/v1/enrol', ['code' => $second, 'csr' => 'not a csr'])->assertJsonValidationErrors('csr');

    $this->travel(31)->minutes();
    $this->postJson('/device/v1/enrol', ['code' => $second, 'csr' => $csr])->assertUnprocessable();
});

it('keeps one device per screen: enrolling a new one revokes the old', function () {
    $old = enrolDevice($this->owner, $this->screen, 'device-1.csr');
    $new = enrolDevice($this->owner, $this->screen, 'device-2.csr');

    asDevice($old)->getJson('/device/v1/manifest')->assertForbidden();
    asDevice($new)->getJson('/device/v1/manifest')->assertOk();
    expect(Device::whereNull('revoked_at')->count())->toBe(1);
});

it('needs a certificate on every device request, and records when the device was last seen', function () {
    asDevice(null)->getJson('/device/v1/manifest')->assertUnauthorized();
    asDevice(str_repeat('ab', 20))->getJson('/device/v1/manifest')->assertForbidden();

    $fp = enrolDevice($this->owner, $this->screen);
    asDevice($fp)->withHeader('X-Player-Version', '0.2.0')->getJson('/device/v1/manifest')
        ->assertOk()->assertJsonPath('data', null); // nothing signed yet

    actingAsApi($this->owner)->getJson("/api/v1/orgs/{$this->org->uuid}/screens")->assertOk()
        ->assertJsonPath('data.0.device.online', true)
        ->assertJsonPath('data.0.device.player_version', '0.2.0');
});

it('signs a playlist of booked hours that the device can verify, and re-signs only on change', function () {
    Storage::fake('local');
    // 10:00 in Kathmandu on 10 November.
    $this->travelTo(CarbonImmutable::parse('2026-11-10 10:00', 'Asia/Kathmandu'));
    $fp = enrolDevice($this->owner, $this->screen);

    Storage::disk('local')->put('creatives/a/processed.webp', 'image bytes');
    $ad = Creative::factory()->create([
        'processed_path' => 'creatives/a/processed.webp', 'sha256' => hash('sha256', 'image bytes'),
    ]);
    $booking = playableBooking($this->screen, $ad, '2026-11-09', '2026-11-12', 8, 10, status: 'live');
    playableBooking($this->screen, Creative::factory()->inReview()->create(['sha256' => str_repeat('c', 64), 'processed_path' => 'x']), '2026-11-10', '2026-11-10', 12, 13);

    $this->artisan('manifests:sign')->expectsOutput('1 manifests signed.')->assertSuccessful();
    $payload = openManifest(asDevice($fp)->getJson('/device/v1/manifest')->assertOk()->json('data'), $this->publicKey);

    $day = fn (string $d, int $h) => CarbonImmutable::parse("{$d} {$h}:00", 'Asia/Kathmandu')->getTimestamp();
    expect($payload)->toMatchArray([
        'screen_id' => $this->screen->uuid,
        'tz_offset_s' => 20700,
        'loop_seconds' => 120,
        'spot_seconds' => 10,
        'expires_at' => $day('2026-11-12', 0),
    ])->and($payload['version'])->toBeGreaterThanOrEqual(now()->getTimestamp())
        ->and($payload['valid_from'])->toBeLessThan(now()->getTimestamp())
        // Only the approved ad, for today's and tomorrow's booked hours.
        ->and($payload['items'])->toBe([[
            'creative_id' => $ad->uuid,
            'url' => "/device/v1/media/{$ad->uuid}",
            'sha256' => hash('sha256', 'image bytes'),
            'duration_s' => 10,
            'slot_index' => 0, // alone in the hour, so packed at the start of the loop
            'slot_count' => 1,
            'windows' => [[$day('2026-11-10', 8), $day('2026-11-10', 10)], [$day('2026-11-11', 8), $day('2026-11-11', 10)]],
        ]]);

    $this->artisan('manifests:sign')->expectsOutput('0 manifests signed.');

    $booking->update(['status' => 'cancelled']);
    $this->travel(1)->minutes();
    $this->artisan('manifests:sign')->expectsOutput('1 manifests signed.');
    $next = openManifest(asDevice($fp)->getJson('/device/v1/manifest')->json('data'), $this->publicKey);
    expect($next['version'])->toBeGreaterThan($payload['version'])->and($next['items'])->toBe([]);
});

it('lets the device download only ads booked on its screen', function () {
    Storage::fake('local');
    $fp = enrolDevice($this->owner, $this->screen);
    Storage::disk('local')->put('creatives/a/processed.webp', 'image bytes');
    $ad = Creative::factory()->create(['processed_path' => 'creatives/a/processed.webp', 'sha256' => hash('sha256', 'image bytes')]);
    $today = now('Asia/Kathmandu')->toDateString();

    asDevice($fp)->get("/device/v1/media/{$ad->uuid}")->assertNotFound();

    playableBooking($this->screen, $ad, $today, $today, 8, 9);
    $res = asDevice($fp)->get("/device/v1/media/{$ad->uuid}")->assertOk()->assertHeader('Content-Type', 'image/webp');
    expect($res->streamedContent())->toBe('image bytes');
});

it('stops a revoked device at once', function () {
    $fp = enrolDevice($this->owner, $this->screen);

    actingAsApi($this->owner)->postJson("{$this->base}/revoke")->assertForbidden(); // two-factor first
    actingAsApi($this->owner, mfa: true)->postJson("{$this->base}/revoke")->assertOk();
    asDevice($fp)->getJson('/device/v1/manifest')->assertForbidden();

    actingAsApi($this->owner, mfa: true)->postJson("{$this->base}/revoke")->assertConflict();
    actingAsApi($this->owner)->getJson($this->base)->assertOk()->assertJsonPath('data', null);
});
