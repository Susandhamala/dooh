<?php

use App\Models\Organisation;
use App\Models\User;

beforeEach(function () {
    $this->org = Organisation::factory()->owner()->create();
    $this->admin = User::factory()->withMfa()->create()->assignRole('admin');
});

it('blocks non-admins from admin endpoints', function () {
    $user = User::factory()->withMfa()->create();

    actingAsApi($user, mfa: true)->getJson('/api/v1/admin/orgs')->assertForbidden();
    actingAsApi($user, mfa: true)->postJson("/api/v1/admin/orgs/{$this->org->uuid}/verify")->assertForbidden();
    expect($this->org->fresh()->isVerified())->toBeFalse();
});

it('requires admins to be two-factor verified', function () {
    actingAsApi($this->admin)->postJson("/api/v1/admin/orgs/{$this->org->uuid}/verify")->assertForbidden();
    expect($this->org->fresh()->isVerified())->toBeFalse();
});

it('lets a verified admin verify and suspend organisations', function () {
    actingAsApi($this->admin, mfa: true)->getJson('/api/v1/admin/orgs?verified=0')->assertOk()->assertJsonPath('meta.total', 1);
    actingAsApi($this->admin, mfa: true)->postJson("/api/v1/admin/orgs/{$this->org->uuid}/verify")
        ->assertOk()->assertJsonPath('data.verified', true);
    actingAsApi($this->admin, mfa: true)->postJson("/api/v1/admin/orgs/{$this->org->uuid}/suspend")
        ->assertOk()->assertJsonPath('data.status', 'suspended');
});

it('cannot be self-granted by creating an organisation', function () {
    $user = User::factory()->create();

    actingAsApi($user)->postJson('/api/v1/orgs', ['name' => 'Sneaky', 'type' => 'admin'])->assertUnprocessable();
    expect($user->fresh()->hasRole('admin'))->toBeFalse();
});

it('grants roles only through the make-admin command', function () {
    $user = User::factory()->create(['email' => 'boss@x.np']);

    $this->artisan('dooh:make-admin', ['email' => 'BOSS@x.np'])->assertSuccessful();
    expect($user->fresh()->hasRole('admin'))->toBeTrue();
});
