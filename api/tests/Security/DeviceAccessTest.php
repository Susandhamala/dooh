<?php

use App\Models\Creative;
use App\Models\Organisation;
use App\Models\Screen;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    useTestDeviceKeys();
    $this->owner = User::factory()->withMfa()->create();
    $this->org = Organisation::factory()->owner()->verified()->withMember($this->owner)->create();
    $this->screen = Screen::factory()->create(['organisation_id' => $this->org]);

    $this->rival = User::factory()->withMfa()->create();
    $this->rivalOrg = Organisation::factory()->owner()->verified()->withMember($this->rival)->create();
    $this->rivalScreen = Screen::factory()->create(['organisation_id' => $this->rivalOrg]);
});

it("hides another owner's screen device: same 404 as a screen that doesn't exist", function () {
    enrolDevice($this->owner, $this->screen);
    $theirs = "/api/v1/orgs/{$this->org->uuid}/screens/{$this->screen->uuid}/device";
    $mixed = "/api/v1/orgs/{$this->rivalOrg->uuid}/screens/{$this->screen->uuid}/device";

    foreach ([$theirs, $mixed] as $url) {
        actingAsApi($this->rival, mfa: true)->getJson($url)->assertNotFound();
        actingAsApi($this->rival, mfa: true)->postJson("{$url}/code")->assertNotFound();
        actingAsApi($this->rival, mfa: true)->postJson("{$url}/revoke")->assertNotFound();
    }
    actingAsApi($this->rival, mfa: true)->getJson("/api/v1/orgs/{$this->rivalOrg->uuid}/screens/".fake()->uuid().'/device')->assertNotFound();
    $this->assertDatabaseCount('device_enrolment_codes', 1);
});

it('does not let a read-only member connect or disconnect devices', function () {
    $viewer = User::factory()->withMfa()->create();
    $this->org->members()->attach($viewer, ['role' => 'viewer']);
    $url = "/api/v1/orgs/{$this->org->uuid}/screens/{$this->screen->uuid}/device";

    actingAsApi($viewer, mfa: true)->getJson($url)->assertOk();
    actingAsApi($viewer, mfa: true)->postJson("{$url}/code")->assertForbidden();
    actingAsApi($viewer, mfa: true)->postJson("{$url}/revoke")->assertForbidden();
});

it("keeps a device to its own screen's playlist and files", function () {
    Storage::fake('local');
    useTestDeviceKeys();
    $mine = enrolDevice($this->owner, $this->screen, 'device-1.csr');
    $theirs = enrolDevice($this->rival, $this->rivalScreen, 'device-2.csr');

    Storage::disk('local')->put('creatives/r/processed.webp', 'their ad');
    $ad = Creative::factory()->create(['processed_path' => 'creatives/r/processed.webp', 'sha256' => hash('sha256', 'their ad')]);
    $today = now('Asia/Kathmandu')->toDateString();
    playableBooking($this->rivalScreen, $ad, $today, $today, 0, 24);

    $this->artisan('manifests:sign')->expectsOutput('2 manifests signed.');
    $payload = json_decode(base64_decode(asDevice($mine)->getJson('/device/v1/manifest')->json('data.payload')), true);
    expect($payload['screen_id'])->toBe($this->screen->uuid)->and($payload['items'])->toBe([]);

    asDevice($mine)->get("/device/v1/media/{$ad->uuid}")->assertNotFound();
    asDevice($theirs)->get("/device/v1/media/{$ad->uuid}")->assertOk();
});

it('cannot be authenticated by a header pretending to be the certificate fingerprint', function () {
    $fp = enrolDevice($this->owner, $this->screen);
    resetAuth();

    $this->withHeaders(['DEVICE_CERT_FINGERPRINT' => $fp, 'Device-Cert-Fingerprint' => $fp])
        ->getJson('/device/v1/manifest')->assertUnauthorized();
});

it('rate-limits enrolment attempts so codes cannot be guessed', function () {
    $csr = file_get_contents(base_path('tests/Fixtures/devices/device-1.csr'));
    foreach (range(1, 5) as $i) {
        $this->postJson('/device/v1/enrol', ['code' => "AAAA-AAA{$i}", 'csr' => $csr])->assertUnprocessable();
    }
    $this->postJson('/device/v1/enrol', ['code' => 'AAAA-AAAA', 'csr' => $csr])->assertTooManyRequests();
});
