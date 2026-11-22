<?php

use App\Enums\WalletEntryType;
use App\Models\Booking;
use App\Models\Creative;
use App\Models\Organisation;
use App\Models\Screen;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Devices\ManifestSigner;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', 'Security');

/**
 * Forget the authenticated user and the parsed token between requests in one test.
 * (Under PHP-FPM each request is a fresh process; in tests the container is shared.)
 */
function resetAuth(): void
{
    auth()->forgetGuards();
    app('tymon.jwt')->unsetToken();
    JWTAuth::unsetToken();
}

/** A bearer token for $user, as if they signed in (and optionally passed two-factor). */
function tokenFor(User $user, bool $mfa = false): string
{
    resetAuth();

    return auth('api')->claims(['mfa' => $mfa])->tokenById($user->uuid);
}

/** Send a JSON request as $user. Guards are reset first so no identity leaks between requests. */
function actingAsApi(User $user, bool $mfa = false): TestCase
{
    $token = tokenFor($user, $mfa);
    resetAuth();

    return test()->withToken($token);
}

function totp(string $secret, int $offsetSteps = 0): string
{
    $g = new Google2FA;

    return $g->oathTotp($secret, $g->getTimestamp() + $offsetSteps);
}

function envelopeError(TestResponse $response): ?string
{
    return $response->json('message');
}

/** The user has accepted the current terms and content policy. */
function acceptTerms(User $user): User
{
    $user->termsAcceptances()->create([
        'version' => config('terms.version'), 'text_sha256' => str_repeat('a', 64), 'accepted_at' => now(),
    ]);

    return $user;
}

/** The user has an approved identity document (file already deleted, as after a real review). */
function verifyIdentity(User $user, string $status = 'approved'): User
{
    $user->identityDocuments()->create([
        'type' => 'citizenship', 'number' => '12-34-56-7890', 'mime' => 'image/jpeg', 'sha256' => str_repeat('b', 64), 'status' => $status,
    ]);

    return $user;
}

/** Put $rupees into an organisation's wallet (as a top-up would). */
function fundWallet(Organisation $org, int $rupees): Wallet
{
    $wallet = Wallet::firstOrCreate(['organisation_id' => $org->getKey()]);
    $wallet->increment('balance_paisa', $rupees * 100);
    $wallet->transactions()->create([
        'type' => WalletEntryType::TopUp, 'amount_paisa' => $rupees * 100, 'balance_after_paisa' => $wallet->balance_paisa,
        'reserved_after_paisa' => $wallet->reserved_paisa, 'description' => 'Test top-up', 'created_at' => now(),
    ]);

    return $wallet->refresh();
}

/**
 * Point the device CA and manifest signer at test-only keys (tests/Fixtures/devices).
 *
 * @return string the base64 manifest public key devices verify against
 */
function useTestDeviceKeys(): string
{
    $seed = tempnam(sys_get_temp_dir(), 'seed');
    file_put_contents($seed, base64_encode(str_repeat("\x07", SODIUM_CRYPTO_SIGN_SEEDBYTES)));
    config([
        'dooh.devices.ca_cert_path' => base_path('tests/Fixtures/devices/ca.crt'),
        'dooh.devices.ca_key_path' => base_path('tests/Fixtures/devices/ca.key'),
        'dooh.manifest.signing_key_path' => $seed,
        'dooh.manifest.public_key' => null,
    ]);

    return ManifestSigner::publicKeyFromSeed(file_get_contents($seed));
}

/** Get an enrolment code for $screen as its owner, enrol a device with it, return its fingerprint. */
function enrolDevice(User $owner, Screen $screen, string $csr = 'device-1.csr'): string
{
    $code = actingAsApi($owner, mfa: true)
        ->postJson("/api/v1/orgs/{$screen->organisation->uuid}/screens/{$screen->uuid}/device/code")
        ->assertCreated()->json('data.code');
    resetAuth();
    $cert = test()->postJson('/device/v1/enrol', ['code' => $code, 'csr' => file_get_contents(base_path("tests/Fixtures/devices/{$csr}"))])
        ->assertCreated()->json('data.certificate');

    return openssl_x509_fingerprint($cert, 'sha1');
}

/** A request as the device holding the certificate with $fingerprint (what Nginx passes after mTLS). */
function asDevice(?string $fingerprint): TestCase
{
    resetAuth();

    return test()->withServerVariables(['DEVICE_CERT_FINGERPRINT' => $fingerprint ?? '']);
}

/** A booking that is ready to play: ad approved and processed, status scheduled. */
function playableBooking(Screen $screen, Creative $creative, string $from, string $to, int $startHour, int $endHour, string $status = 'scheduled'): Booking
{
    return Booking::create([
        'organisation_id' => $creative->organisation_id, 'screen_id' => $screen->getKey(), 'creative_id' => $creative->getKey(),
        'status' => $status, 'price' => 0, 'price_per_play' => 2, 'plays_total' => 100,
        'date_range' => Booking::dateRange(CarbonImmutable::parse($from), CarbonImmutable::parse($to)),
        'start_minute' => $startHour * 60, 'end_minute' => $endHour * 60,
    ]);
}
