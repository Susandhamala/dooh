<?php

use App\Models\User;

it('creates an organisation and makes the creator its admin', function () {
    $user = User::factory()->create();

    actingAsApi($user)->postJson('/api/v1/orgs', ['name' => 'Himalayan Tea', 'type' => 'advertiser', 'pan_number' => '601234567'])
        ->assertCreated()
        ->assertJsonPath('data.type', 'advertiser')
        ->assertJsonPath('data.verified', false);

    actingAsApi($user)->getJson('/api/v1/orgs')->assertJsonPath('data.0.my_role', 'admin');
    expect($user->fresh()->hasRole('advertiser'))->toBeTrue();
});

it('validates the PAN format', function () {
    actingAsApi(User::factory()->create())->postJson('/api/v1/orgs', ['name' => 'X', 'type' => 'owner', 'pan_number' => '12AB'])
        ->assertUnprocessable()->assertJsonValidationErrors('pan_number');
});
