<?php

namespace App\Repositories;

use App\Enums\MembershipRole;
use App\Models\Membership;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class OrganisationRepository
{
    /** @return Collection<int, Organisation> */
    public function forUser(User $user): Collection
    {
        return $user->organisations()->orderBy('name')->get();
    }

    /** Membership of $user in organisation $uuid, or null. Null for "not found" and "not a member" alike. */
    public function membership(User $user, string $uuid): ?Membership
    {
        return Membership::query()
            ->where('user_id', $user->getKey())
            ->whereHas('organisation', fn ($q) => $q->where('uuid', $uuid))
            ->with('organisation')
            ->first();
    }

    public function create(array $attributes, User $creator): Organisation
    {
        $org = Organisation::create($attributes);
        Membership::create([
            'user_id' => $creator->getKey(),
            'organisation_id' => $org->getKey(),
            'role' => MembershipRole::Admin,
        ]);

        return $org;
    }

    public function findByUuid(string $uuid): ?Organisation
    {
        return Organisation::where('uuid', $uuid)->first();
    }

    public function save(Organisation $org): Organisation
    {
        $org->save();

        return $org;
    }

    public function paginateForAdmin(?bool $verified, int $perPage = 25): LengthAwarePaginator
    {
        return Organisation::query()
            ->when($verified === true, fn ($q) => $q->whereNotNull('verified_at'))
            ->when($verified === false, fn ($q) => $q->whereNull('verified_at'))
            ->latest()
            ->paginate($perPage);
    }
}
