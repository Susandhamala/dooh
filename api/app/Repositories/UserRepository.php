<?php

namespace App\Repositories;

use App\Models\User;

class UserRepository
{
    public function findByEmail(string $email): ?User
    {
        return User::where('email', mb_strtolower($email))->first();
    }

    public function findByRentallUuid(string $uuid): ?User
    {
        return User::where('rentall_uuid', $uuid)->first();
    }

    public function create(array $attributes): User
    {
        $attributes['email'] = mb_strtolower($attributes['email']);

        return User::create($attributes);
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
