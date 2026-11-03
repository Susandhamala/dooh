<?php

namespace App\Repositories;

use App\Models\TermsAcceptance;
use App\Models\User;

class UserRepository
{
    public function findByEmail(string $email): ?User
    {
        return User::where('email', mb_strtolower($email))->first();
    }

    public function findByPhone(string $e164): ?User
    {
        return User::where('phone', $e164)->first();
    }

    public function findByRentallUuid(string $uuid): ?User
    {
        return User::where('rentall_uuid', $uuid)->first();
    }

    public function create(array $attributes): User
    {
        if (isset($attributes['email'])) {
            $attributes['email'] = mb_strtolower($attributes['email']);
        }

        return User::create($attributes);
    }

    public function hasAcceptedTerms(User $user, string $version): bool
    {
        return $user->termsAcceptances()->where('version', $version)->exists();
    }

    public function recordTerms(User $user, array $attributes): TermsAcceptance
    {
        return $user->termsAcceptances()->firstOrCreate(['version' => $attributes['version']], $attributes);
    }

    public function save(User $user): User
    {
        $user->save();

        return $user;
    }

    /**
     * Store the TOTP time step only if it is newer than the last one, atomically,
     * so two concurrent requests can't both use the same code.
     */
    public function claimMfaTimestep(User $user, int $timestep): bool
    {
        $updated = User::whereKey($user->getKey())
            ->where(fn ($q) => $q->whereNull('mfa_last_timestep')->orWhere('mfa_last_timestep', '<', $timestep))
            ->update(['mfa_last_timestep' => $timestep]);

        return $updated === 1;
    }
}
