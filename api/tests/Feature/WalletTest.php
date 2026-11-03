<?php

use App\Models\Organisation;
use App\Models\User;
use Illuminate\Database\QueryException;

beforeEach(function () {
    $this->user = acceptTerms(User::factory()->create());
    $this->org = Organisation::factory()->withMember($this->user)->create();
    $this->url = "/api/v1/orgs/{$this->org->uuid}/wallet";
});

it('starts empty and loads money through a chosen gateway', function () {
    actingAsApi($this->user)->getJson($this->url)->assertOk()->assertJsonPath('data.balance', '0.00');

    actingAsApi($this->user)->postJson("{$this->url}/topup", ['amount' => 2500, 'gateway' => 'khalti'])
        ->assertCreated()
        ->assertJsonPath('data.wallet.balance', '2500.00')
        ->assertJsonPath('data.wallet.available', '2500.00')
        ->assertJsonPath('data.transaction.type', 'topup')
        ->assertJsonPath('data.transaction.description', 'Money loaded via Khalti');

    actingAsApi($this->user)->getJson("{$this->url}/transactions")->assertOk()->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.amount', '2500.00');
});

it('keeps top-ups within the limits and to known gateways', function () {
    actingAsApi($this->user)->postJson("{$this->url}/topup", ['amount' => 50, 'gateway' => 'esewa'])->assertJsonValidationErrors('amount');
    actingAsApi($this->user)->postJson("{$this->url}/topup", ['amount' => 9_000_000, 'gateway' => 'esewa'])->assertJsonValidationErrors('amount');
    actingAsApi($this->user)->postJson("{$this->url}/topup", ['amount' => 500, 'gateway' => 'paypal'])->assertJsonValidationErrors('gateway');
    actingAsApi($this->user)->postJson("{$this->url}/topup", ['amount' => -500, 'gateway' => 'esewa'])->assertJsonValidationErrors('amount');
});

it('never lets the balance go below what is reserved (database check)', function () {
    $wallet = fundWallet($this->org, 100);
    $wallet->reserved_paisa = 20000;

    expect(fn () => $wallet->save())->toThrow(QueryException::class);
});
