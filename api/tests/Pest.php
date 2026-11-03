<?php

use App\Enums\WalletEntryType;
use App\Models\Organisation;
use App\Models\User;
use App\Models\Wallet;
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
