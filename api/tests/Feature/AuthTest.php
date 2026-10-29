<?php

use App\Models\User;

it('registers and signs in with a local lab account', function () {
    $this->postJson('/api/v1/auth/register', [
        'name' => 'Sita', 'email' => 'Sita@Example.com', 'password' => 'correct horse 42',
    ])->assertCreated()->assertJsonPath('data.user.email', 'sita@example.com');

    $res = $this->postJson('/api/v1/auth/login', ['email' => 'sita@example.com', 'password' => 'correct horse 42'])
        ->assertOk()
        ->assertJsonPath('data.mfa_verified', false)
        ->assertJsonStructure(['data' => ['token', 'expires_in', 'user' => ['id', 'roles']]]);

    resetAuth();
    $this->withToken($res->json('data.token'))->getJson('/api/v1/auth/me')
        ->assertOk()->assertJsonPath('data.email', 'sita@example.com');
});

it('stores passwords with argon2id', function () {
    $this->postJson('/api/v1/auth/register', ['name' => 'A', 'email' => 'a@x.np', 'password' => 'correct horse 42']);

    expect(User::first()->password)->toStartWith('$argon2id$');
});

it('rejects weak passwords and duplicate emails', function () {
    User::factory()->create(['email' => 'taken@x.np']);

    $this->postJson('/api/v1/auth/register', ['name' => 'A', 'email' => 'new@x.np', 'password' => 'short'])
        ->assertUnprocessable()->assertJsonValidationErrors('password');
    $this->postJson('/api/v1/auth/register', ['name' => 'A', 'email' => 'TAKEN@x.np', 'password' => 'correct horse 42'])
        ->assertUnprocessable()->assertJsonValidationErrors('email');
});

it('gives the same answer for a wrong password and an unknown email', function () {
    User::factory()->create(['email' => 'real@x.np']);

    $a = $this->postJson('/api/v1/auth/login', ['email' => 'real@x.np', 'password' => 'wrong'])->assertUnauthorized();
    $b = $this->postJson('/api/v1/auth/login', ['email' => 'ghost@x.np', 'password' => 'wrong'])->assertUnauthorized();

    expect($a->json('message'))->toBe($b->json('message'));
});

it('disables local login outside the lab', function () {
    config(['dooh.lab_local_login' => false]);
    User::factory()->create(['email' => 'real@x.np']);

    $this->postJson('/api/v1/auth/login', ['email' => 'real@x.np', 'password' => 'password'])->assertForbidden();
    $this->postJson('/api/v1/auth/register', ['name' => 'A', 'email' => 'n@x.np', 'password' => 'correct horse 42'])->assertForbidden();
});

it('rate limits login attempts', function () {
    foreach (range(1, 5) as $_) {
        $this->postJson('/api/v1/auth/login', ['email' => 'x@x.np', 'password' => 'nope'])->assertUnauthorized();
    }
    $this->postJson('/api/v1/auth/login', ['email' => 'x@x.np', 'password' => 'nope'])->assertTooManyRequests();
});

it('refuses suspended users at login and on existing tokens', function () {
    $user = User::factory()->create(['email' => 's@x.np']);
    $token = tokenFor($user);
    $user->forceFill(['status' => User::STATUS_SUSPENDED])->save();

    $this->postJson('/api/v1/auth/login', ['email' => 's@x.np', 'password' => 'password'])->assertForbidden();
    resetAuth();
    $this->withToken($token)->getJson('/api/v1/auth/me')->assertForbidden();
});

it('invalidates the token on logout', function () {
    $token = tokenFor(User::factory()->create());

    $this->withToken($token)->postJson('/api/v1/auth/logout')->assertOk();
    resetAuth();
    $this->withToken($token)->getJson('/api/v1/auth/me')->assertUnauthorized();
});

it('keeps the mfa claim when refreshing', function () {
    $token = tokenFor(User::factory()->withMfa()->create(), mfa: true);

    $new = $this->withToken($token)->postJson('/api/v1/auth/refresh')->assertOk()->json('data.token');
    resetAuth();
    $this->withToken($new)->getJson('/api/v1/auth/me')->assertJsonPath('meta.mfa_verified', true);
});
