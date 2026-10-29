<?php

use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

$code = str_repeat('a1', 32);

function fakeRentall(array $data, int $status = 200): void
{
    Http::swap(new Factory);
    Http::fake(['rentall.test/api/v1/sso/ads/exchange' => Http::response(['success' => $status < 300, 'data' => $data], $status)]);
}

$identity = [
    'uuid' => '0b9d7a4e-8f1c-4e43-9f5e-1a2b3c4d5e6f',
    'name' => 'Ram Shrestha',
    'email' => 'ram@example.com',
    'phone' => '9800000000',
    'email_verified' => true,
    'phone_verified' => false,
];

it('creates a local user from a rentallnepal identity', function () use ($code, $identity) {
    fakeRentall($identity);

    $this->postJson('/api/v1/auth/sso', ['code' => $code])
        ->assertOk()
        ->assertJsonPath('data.user.email', 'ram@example.com')
        ->assertJsonPath('data.user.email_verified', true)
        ->assertJsonPath('data.user.phone_verified', false);

    expect(User::where('rentall_uuid', $identity['uuid'])->count())->toBe(1);

    // The secret goes server-to-server as a bearer token, bound to our callback URL.
    Http::assertSent(fn (Request $r) => $r->hasHeader('Authorization', 'Bearer test-client-secret')
        && $r['code'] === $code
        && str_ends_with($r['redirect_uri'], '/sso/callback'));
});

it('updates the same user on the next sign-in', function () use ($code, $identity) {
    fakeRentall($identity);
    $this->postJson('/api/v1/auth/sso', ['code' => $code])->assertOk();

    fakeRentall([...$identity, 'name' => 'Ram B. Shrestha']);
    $this->postJson('/api/v1/auth/sso', ['code' => $code])->assertOk();

    expect(User::count())->toBe(1)->and(User::first()->name)->toBe('Ram B. Shrestha');
});

it('refuses when rentallnepal rejects the code (expired, reused or disabled account)', function () use ($code) {
    fakeRentall([], 401);

    $this->postJson('/api/v1/auth/sso', ['code' => $code])->assertUnauthorized()->assertJsonPath('success', false);
    expect(User::count())->toBe(0);
});

it('does not take over a local account with the same email', function () use ($code, $identity) {
    User::factory()->create(['email' => 'ram@example.com']);
    fakeRentall($identity);

    $this->postJson('/api/v1/auth/sso', ['code' => $code])->assertConflict();
    expect(User::whereNotNull('rentall_uuid')->count())->toBe(0);
});

it('rejects malformed codes before calling rentallnepal', function () {
    Http::fake();

    $this->postJson('/api/v1/auth/sso', ['code' => 'short'])->assertUnprocessable();
    $this->postJson('/api/v1/auth/sso', ['code' => str_repeat('a', 40).'"; DROP'])->assertUnprocessable();
    Http::assertNothingSent();
});

it('reports rentallnepal outages as 503', function () use ($code) {
    Http::fake(fn () => throw new ConnectionException('down'));

    $this->postJson('/api/v1/auth/sso', ['code' => $code])->assertStatus(503);
});
