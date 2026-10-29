<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::middleware('auth:api')->get('/api/v1/_probe', fn () => ['uuid' => auth()->user()->uuid]);
});

it('rejects requests without a token', function () {
    $this->getJson('/api/v1/_probe')->assertUnauthorized()->assertJson(['success' => false]);
});

it('rejects a forged token', function () {
    $this->withToken('eyJhbGciOiJIUzI1NiJ9.eyJzdWIiOiIxIn0.forged')
        ->getJson('/api/v1/_probe')
        ->assertUnauthorized();
});

it('accepts a token issued by the api guard and resolves the user by uuid', function () {
    $user = User::factory()->create();
    $token = auth('api')->login($user);

    $this->withToken($token)->getJson('/api/v1/_probe')->assertOk()->assertJson(['uuid' => $user->uuid]);
});
