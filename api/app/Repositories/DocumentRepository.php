<?php

namespace App\Repositories;

use App\Enums\ReviewStatus;
use App\Models\IdentityDocument;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class DocumentRepository
{
    /** @return Collection<int, IdentityDocument> */
    public function forUser(User $user): Collection
    {
        return $user->identityDocuments()->latest('id')->get();
    }

    public function latestForUser(User $user): ?IdentityDocument
    {
        return $user->identityDocuments()->latest('id')->first();
    }

    public function hasPending(User $user): bool
    {
        return $user->identityDocuments()->where('status', ReviewStatus::Pending)->exists();
    }

    public function create(User $user, array $attributes): IdentityDocument
    {
        return $user->identityDocuments()->create($attributes);
    }

    public function findByUuid(string $uuid): ?IdentityDocument
    {
        return IdentityDocument::with('user')->where('uuid', $uuid)->first();
    }

    public function paginateByStatus(?ReviewStatus $status, int $perPage = 25): LengthAwarePaginator
    {
        return IdentityDocument::with('user')
            ->when($status, fn ($q) => $q->where('status', $status))
            ->orderBy('created_at')
            ->paginate($perPage);
    }

    public function save(IdentityDocument $document): IdentityDocument
    {
        $document->save();

        return $document;
    }
}
