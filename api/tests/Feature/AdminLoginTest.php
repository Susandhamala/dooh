<?php

use App\Models\User;
use App\Services\AdminService;

beforeEach(function () {
    app(AdminService::class)->setPasswordLogin('Susan', 'secret-pass', 'Susan');
});

it('creates an admin with a lower-case username and a hashed password', function () {
    $user = User::where('username', 'susan')->firstOrFail();

    expect($user->hasRole('admin'))->toBeTrue()
        ->and($user->password)->not->toBe('secret-pass');
});

it('signs an admin in with username and password, straight into the admin panel', function () {
    $token = $this->postJson('/api/v1/auth/admin/login', ['username' => 'Susan', 'password' => 'secret-pass'])
        ->assertOk()
        ->assertJsonPath('data.mfa_verified', true)
        ->assertJsonPath('data.user.roles', ['admin'])
        ->json('data.token');

    $this->withToken($token)->getJson('/api/v1/admin/overview')->assertOk();
});

it('refuses a wrong password or an unknown username with the same message', function () {
    $this->postJson('/api/v1/auth/admin/login', ['username' => 'susan', 'password' => 'nope'])
        ->assertUnauthorized()->assertJsonPath('message', 'Wrong username or password.');
    $this->postJson('/api/v1/auth/admin/login', ['username' => 'nobody', 'password' => 'secret-pass'])
        ->assertUnauthorized()->assertJsonPath('message', 'Wrong username or password.');
});

it('refuses an account that is no longer an admin', function () {
    User::where('username', 'susan')->firstOrFail()->removeRole('admin');

    $this->postJson('/api/v1/auth/admin/login', ['username' => 'susan', 'password' => 'secret-pass'])->assertUnauthorized();
});

it('refuses a suspended admin', function () {
    $user = User::where('username', 'susan')->firstOrFail();
    $user->status = User::STATUS_SUSPENDED;
    $user->save();

    $this->postJson('/api/v1/auth/admin/login', ['username' => 'susan', 'password' => 'secret-pass'])->assertForbidden();
});

it('changes the password when run again for the same username', function () {
    expect(app(AdminService::class)->setPasswordLogin('susan', 'new-pass-1'))->toBeFalse();

    $this->postJson('/api/v1/auth/admin/login', ['username' => 'susan', 'password' => 'secret-pass'])->assertUnauthorized();
    $this->postJson('/api/v1/auth/admin/login', ['username' => 'susan', 'password' => 'new-pass-1'])->assertOk();
});
