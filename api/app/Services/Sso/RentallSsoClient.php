<?php

namespace App\Services\Sso;

use App\Exceptions\DomainError;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Server-to-server half of the one-time code exchange with rentallnepal.com.
 * See docs/SSO.md for the contract rentallnepal implements.
 */
class RentallSsoClient
{
    /**
     * @return array{uuid: string, name: string, email: string, phone: ?string, email_verified: bool, phone_verified: bool}
     */
    public function exchange(string $code): array
    {
        try {
            $response = Http::baseUrl(config('dooh.rentall.base_url'))
                ->withToken(config('dooh.rentall.sso_client_secret'))
                ->acceptJson()
                ->timeout(5)
                ->post('/api/v1/sso/ads/exchange', [
                    'code' => $code,
                    'redirect_uri' => rtrim(config('app.url'), '/').'/sso/callback',
                ]);
        } catch (ConnectionException) {
            throw new DomainError('rentallnepal is unreachable. Try again shortly.', 503);
        }

        $data = $response->json('data');
        if (! $response->successful() || ! is_array($data) || empty($data['uuid']) || empty($data['email'])) {
            throw DomainError::unauthenticated('Sign-in with rentallnepal failed or expired. Please try again.');
        }

        return [
            'uuid' => (string) $data['uuid'],
            'name' => (string) ($data['name'] ?? ''),
            'email' => (string) $data['email'],
            'phone' => isset($data['phone']) ? (string) $data['phone'] : null,
            'email_verified' => (bool) ($data['email_verified'] ?? false),
            'phone_verified' => (bool) ($data['phone_verified'] ?? false),
        ];
    }
}
