<?php

use App\Models\User;

it('enrols two-factor and returns a verified session', function () {
    $user = User::factory()->create();

    $secret = actingAsApi($user)->postJson('/api/v1/auth/mfa/setup')->assertOk()->json('data.secret');

    actingAsApi($user)->postJson('/api/v1/auth/mfa/enable', ['code' => totp($secret)])
        ->assertOk()->assertJsonPath('data.mfa_verified', true);
    expect($user->fresh()->hasMfa())->toBeTrue();
});

it('rejects a wrong code during enrolment', function () {
    $user = User::factory()->create();
    actingAsApi($user)->postJson('/api/v1/auth/mfa/setup');

    actingAsApi($user)->postJson('/api/v1/auth/mfa/enable', ['code' => '000000'])->assertUnprocessable();
    expect($user->fresh()->hasMfa())->toBeFalse();
});

it('steps up a session with a valid code', function () {
    $user = User::factory()->withMfa()->create();

    $login = $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'password'])
        ->assertJsonPath('data.mfa_required', true)->json('data.token');

    resetAuth();
    $this->withToken($login)->postJson('/api/v1/auth/mfa/verify', ['code' => totp($user->mfa_secret)])
        ->assertOk()->assertJsonPath('data.mfa_verified', true);
});

it('refuses to accept the same code twice (replay)', function () {
    $user = User::factory()->withMfa()->create();
    $code = totp($user->mfa_secret);

    actingAsApi($user)->postJson('/api/v1/auth/mfa/verify', ['code' => $code])->assertOk();
    actingAsApi($user)->postJson('/api/v1/auth/mfa/verify', ['code' => $code])->assertUnprocessable();
});

it('refuses a code older than the last one used', function () {
    $user = User::factory()->withMfa()->create();

    actingAsApi($user)->postJson('/api/v1/auth/mfa/verify', ['code' => totp($user->mfa_secret)])->assertOk();
    actingAsApi($user)->postJson('/api/v1/auth/mfa/verify', ['code' => totp($user->mfa_secret, -1)])->assertUnprocessable();
});

it('cannot re-run setup to replace an enrolled secret', function () {
    $user = User::factory()->withMfa()->create();

    actingAsApi($user, mfa: true)->postJson('/api/v1/auth/mfa/setup')->assertConflict();
});

it('never exposes the secret on the user resource', function () {
    $user = User::factory()->withMfa()->create();

    $json = actingAsApi($user)->getJson('/api/v1/auth/me')->assertOk()->getContent();
    expect($json)->not->toContain($user->mfa_secret)->not->toContain('mfa_secret');
});
