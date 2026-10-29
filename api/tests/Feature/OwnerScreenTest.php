<?php

use App\Models\Organisation;
use App\Models\User;

beforeEach(function () {
    $this->owner = User::factory()->withMfa()->create();
    $this->org = Organisation::factory()->owner()->verified()->withMember($this->owner)->create();
    $this->payload = [
        'name' => 'New Road Junction', 'lat' => 27.7041, 'lng' => 85.3075,
        'width_px' => 1920, 'height_px' => 1080, 'orientation' => 'landscape',
        'slot_price' => 750, 'content_rules' => ['no_alcohol', 'no_political'],
    ];
});

it('creates a screen with content rules', function () {
    actingAsApi($this->owner, mfa: true)
        ->postJson("/api/v1/orgs/{$this->org->uuid}/screens", $this->payload)
        ->assertCreated()
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.slots_per_loop', 12)
        ->assertJsonPath('data.content_rules', ['no_alcohol', 'no_political']);
});

it('requires a two-factor verified session to create or edit screens', function () {
    actingAsApi($this->owner)
        ->postJson("/api/v1/orgs/{$this->org->uuid}/screens", $this->payload)
        ->assertForbidden()
        ->assertJsonPath('errors.mfa.0', 'verify');

    $noMfa = User::factory()->create();
    $this->org->members()->attach($noMfa, ['role' => 'editor']);
    actingAsApi($noMfa)
        ->postJson("/api/v1/orgs/{$this->org->uuid}/screens", $this->payload)
        ->assertForbidden()
        ->assertJsonPath('errors.mfa.0', 'setup');
});

it('updates rules and status', function () {
    $id = actingAsApi($this->owner, mfa: true)->postJson("/api/v1/orgs/{$this->org->uuid}/screens", $this->payload)->json('data.id');

    actingAsApi($this->owner, mfa: true)
        ->patchJson("/api/v1/orgs/{$this->org->uuid}/screens/{$id}", ['status' => 'active', 'content_rules' => ['no_gambling']])
        ->assertOk()
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('data.content_rules', ['no_gambling']);
});

it('validates loop and location', function () {
    actingAsApi($this->owner, mfa: true)
        ->postJson("/api/v1/orgs/{$this->org->uuid}/screens", [...$this->payload, 'loop_seconds' => 125, 'spot_seconds' => 10, 'lat' => 51.5])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['loop_seconds', 'lat']);
});

it('rejects unknown content rules', function () {
    actingAsApi($this->owner, mfa: true)
        ->postJson("/api/v1/orgs/{$this->org->uuid}/screens", [...$this->payload, 'content_rules' => ['anything_goes']])
        ->assertUnprocessable()->assertJsonValidationErrors('content_rules.0');
});
