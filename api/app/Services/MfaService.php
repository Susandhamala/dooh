<?php

namespace App\Services;

use App\Exceptions\DomainError;
use App\Models\User;
use App\Repositories\UserRepository;
use PragmaRX\Google2FA\Google2FA;

class MfaService
{
    public function __construct(
        private UserRepository $users,
        private AuthService $auth,
        private Google2FA $totp,
    ) {}

    /** Start enrolment: store a fresh secret (not yet active) and return it for the authenticator app. */
    public function setup(User $user): array
    {
        if ($user->hasMfa()) {
            throw DomainError::conflict('Two-factor authentication is already enabled.');
        }

        $secret = $this->totp->generateSecretKey(32);
        $user->mfa_secret = $secret;
        $user->mfa_last_timestep = null;
        $this->users->save($user);

        return [
            'secret' => $secret,
            'otpauth_url' => $this->totp->getQRCodeUrl(config('app.name'), $user->email, $secret),
        ];
    }

    /** Confirm enrolment with a first code; returns an MFA-verified token. */
    public function enable(User $user, string $code): array
    {
        if ($user->hasMfa()) {
            throw DomainError::conflict('Two-factor authentication is already enabled.');
        }
        if (! $user->mfa_secret) {
            throw new DomainError('Start two-factor setup first.');
        }

        $this->check($user, $code);
        $user->mfa_enabled_at = now();
        $this->users->save($user);

        return $this->auth->issue($user, mfa: true);
    }

    /** Step up the current session: a valid code returns a new token carrying mfa=true. */
    public function verify(User $user, string $code): array
    {
        if (! $user->hasMfa()) {
            throw new DomainError('Two-factor authentication is not enabled.');
        }

        $this->check($user, $code);

        return $this->auth->issue($user, mfa: true);
    }

    private function check(User $user, string $code): void
    {
        // With a non-null last step, verifyKeyNewer returns the matched step (or false);
        // one step of clock drift either way.
        $timestep = $this->totp->verifyKeyNewer($user->mfa_secret, $code, $user->mfa_last_timestep ?? 0, 1);

        if (! is_int($timestep) || ! $this->users->claimMfaTimestep($user, $timestep)) {
            throw new DomainError('The code is invalid or has already been used.', 422, ['code' => ['Invalid code.']]);
        }
        $user->mfa_last_timestep = $timestep;
    }
}
