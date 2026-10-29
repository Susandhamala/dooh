<?php

use App\Models\Organisation;
use App\Models\Screen;

it('lists only active screens of verified, active owners', function () {
    $visible = Screen::factory()->create(['name' => 'Visible']);
    Screen::factory()->create(['name' => 'Draft', 'status' => 'draft']);
    Screen::factory()->create(['name' => 'Unverified owner', 'organisation_id' => Organisation::factory()->owner()]);
    $suspended = Organisation::factory()->owner()->verified()->create();
    $suspended->forceFill(['status' => 'suspended'])->save();
    Screen::factory()->create(['name' => 'Suspended owner', 'organisation_id' => $suspended]);

    $this->getJson('/api/v1/screens')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $visible->uuid)
        ->assertJsonPath('data.0.slots_per_loop', 12)
        ->assertJsonPath('meta.total', 1);
});

it('never leaks internal ids', function () {
    Screen::factory()->create();

    $screen = $this->getJson('/api/v1/screens')->json('data.0');
    expect($screen)->not->toHaveKey('organisation_id')
        ->and($screen['id'])->toBeString()->toMatch('/^[0-9a-f-]{36}$/');
});

it('filters by the visible map area', function () {
    Screen::factory()->create(['name' => 'Kathmandu', 'lat' => 27.70, 'lng' => 85.32]);
    Screen::factory()->create(['name' => 'Pokhara', 'lat' => 28.21, 'lng' => 83.98]);

    $this->getJson('/api/v1/screens?bbox=85.2,27.6,85.5,27.8')
        ->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Kathmandu');
});

it('hides a non-public screen from the detail endpoint', function () {
    $draft = Screen::factory()->create(['status' => 'draft']);

    $this->getJson("/api/v1/screens/{$draft->uuid}")->assertNotFound();
});

it('reports free slots per day', function () {
    $screen = Screen::factory()->slots(3)->create();
    $from = now()->addDays(2)->toDateString();
    $to = now()->addDays(4)->toDateString();

    $this->getJson("/api/v1/screens/{$screen->uuid}/availability?from={$from}&to={$to}")
        ->assertOk()
        ->assertJsonPath('data.slots_per_loop', 3)
        ->assertJsonPath("data.days.{$from}", 3);
});

it('caps the availability window', function () {
    $screen = Screen::factory()->create();

    $this->getJson("/api/v1/screens/{$screen->uuid}/availability?from=2026-11-01&to=2027-11-01")
        ->assertUnprocessable()->assertJsonValidationErrors('to');
});
