<?php

use App\Models\User;
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
