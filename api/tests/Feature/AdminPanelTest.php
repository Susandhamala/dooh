<?php

use App\Models\Booking;
use App\Models\Creative;
use App\Models\Organisation;
use App\Models\Screen;
use App\Models\User;
use App\Models\Wallet;

beforeEach(function () {
    $this->admin = User::factory()->withMfa()->create()->assignRole('admin');
    $this->adv = verifyIdentity(acceptTerms(User::factory()->create(['name' => 'Himalayan Java', 'phone' => '+9779801000001'])));
    $this->org = Organisation::factory()->withMember($this->adv)->create(['name' => 'Himalayan Java']);
    fundWallet($this->org, 1000);
});

function bookOne(): string
{
    $day = now('Asia/Kathmandu')->addDays(2)->toDateString();

    return actingAsApi(test()->adv)->postJson('/api/v1/orgs/'.test()->org->uuid.'/bookings', [
        'screen_id' => Screen::factory()->create(['name' => 'New Road'])->uuid,
        'creative_id' => Creative::factory()->create(['organisation_id' => test()->org])->uuid,
        'start_date' => $day, 'end_date' => $day, 'start_time' => '08:00', 'end_time' => '10:00',
    ])->assertCreated()->json('data.id'); // 2 h × 30 plays × रू 2 = रू 120
}

it('is for two-factor-verified admins only', function () {
    actingAsApi($this->adv)->getJson('/api/v1/admin/overview')->assertForbidden();
    actingAsApi($this->admin)->getJson('/api/v1/admin/overview')->assertForbidden();
    actingAsApi($this->adv)->getJson('/api/v1/admin/users')->assertForbidden();
    actingAsApi($this->adv)->getJson('/api/v1/admin/bookings')->assertForbidden();
});

it('shows the numbers on the dashboard', function () {
    bookOne();
    verifyIdentity(User::factory()->create(), 'pending');

    $data = actingAsApi($this->admin, mfa: true)->getJson('/api/v1/admin/overview')->assertOk()->json('data');

    expect($data['users']['total'])->toBe(3)
        ->and($data['documents']['pending'])->toBe(1)
        ->and($data['bookings']['scheduled'])->toBe(1)
        ->and($data['money']['loaded'])->toBe('1000.00')
        ->and($data['money']['paid'])->toBe('120.00')
        ->and($data['money']['balances'])->toBe('880.00')
        ->and($data['daily'])->toHaveCount(14)
        ->and(end($data['daily'])['amount'])->toBe('120.00')
        ->and($data['recent_bookings'][0]['organisation']['name'])->toBe('Himalayan Java');
});

it('lists users with their verification, balance and bookings, and filters them', function () {
    bookOne();
    $pending = verifyIdentity(User::factory()->create(['name' => 'Everest Momo']), 'pending');

    actingAsApi($this->admin, mfa: true)->getJson('/api/v1/admin/users?search=9801')->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.name', 'Himalayan Java')
        ->assertJsonPath('data.0.document_status', 'approved')
        ->assertJsonPath('data.0.balance', '880.00')
        ->assertJsonPath('data.0.bookings', 1);

    actingAsApi($this->admin, mfa: true)->getJson('/api/v1/admin/users?status=pending')->assertOk()
        ->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $pending->uuid);
    actingAsApi($this->admin, mfa: true)->getJson('/api/v1/admin/users?status=none')->assertOk()
        ->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $this->admin->uuid);
});

it('suspends a user, which stops their session at once, and reinstates them', function () {
    actingAsApi($this->admin, mfa: true)->postJson("/api/v1/admin/users/{$this->adv->uuid}/suspend")
        ->assertOk()->assertJsonPath('data.status', 'suspended');
    actingAsApi($this->adv)->getJson('/api/v1/auth/me')->assertForbidden();

    actingAsApi($this->admin, mfa: true)->postJson("/api/v1/admin/users/{$this->adv->uuid}/reinstate")
        ->assertOk()->assertJsonPath('data.status', 'active');
    actingAsApi($this->adv)->getJson('/api/v1/auth/me')->assertOk();

    actingAsApi($this->admin, mfa: true)->postJson("/api/v1/admin/users/{$this->admin->uuid}/suspend")->assertConflict();
});

it('lists every booking and can cancel one with a refund', function () {
    $id = bookOne();

    actingAsApi($this->admin, mfa: true)->getJson('/api/v1/admin/bookings?status=active&search=new road')->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.organisation.name', 'Himalayan Java')
        ->assertJsonPath('data.0.screen.name', 'New Road');

    actingAsApi($this->admin, mfa: true)->postJson("/api/v1/admin/bookings/{$id}/cancel")
        ->assertOk()->assertJsonPath('data.status', 'cancelled');

    expect(Wallet::where('organisation_id', $this->org->id)->value('balance_paisa'))->toBe(100000)
        ->and(Booking::where('uuid', $id)->value('status')->value)->toBe('cancelled');
    actingAsApi($this->admin, mfa: true)->postJson("/api/v1/admin/bookings/{$id}/cancel")->assertConflict();
});

it('counts paid money net of refunds for each day', function () {
    $id = bookOne();
    actingAsApi($this->adv)->postJson("/api/v1/orgs/{$this->org->uuid}/bookings/{$id}/cancel")->assertOk();

    $data = actingAsApi($this->admin, mfa: true)->getJson('/api/v1/admin/overview')->json('data');
    expect($data['money']['paid'])->toBe('0.00')->and(end($data['daily'])['amount'])->toBe('0.00');
});
