<?php

namespace App\Services\Sso;

use App\Exceptions\DomainError;
use App\Repositories\UserRepository;
use App\Services\AuthService;
use Illuminate\Support\Facades\DB;

class SsoService
{
    public function __construct(
        private RentallSsoClient $client,
        private UserRepository $users,
        private AuthService $auth,
    ) {}

    /** Trade a rentallnepal one-time code for an ads session, creating or updating the local user. */
    public function login(string $code): array
    {
        $identity = $this->client->exchange($code);

        $user = DB::transaction(function () use ($identity) {
            $user = $this->users->findByRentallUuid($identity['uuid']);

            if (! $user) {
                // Never silently merge into a local account that happens to share the email.
                if ($this->users->findByEmail($identity['email'])) {
                    throw DomainError::conflict('An ads account with this email already exists. Contact support to link it.');
                }

                return $this->users->create([
                    'rentall_uuid' => $identity['uuid'],
                    'name' => $identity['name'],
                    'email' => $identity['email'],
                    'phone' => $identity['phone'],
                ]);
            }

            $user->fill(['name' => $identity['name'], 'email' => mb_strtolower($identity['email']), 'phone' => $identity['phone']]);

            return $this->users->save($user);
        });

        $user->email_verified_at = $identity['email_verified'] ? ($user->email_verified_at ?? now()) : null;
        $user->phone_verified_at = $identity['phone_verified'] ? ($user->phone_verified_at ?? now()) : null;
        $this->users->save($user);

        return $this->auth->issue($user);
    }
}
