<?php

namespace App\Repositories;

use App\Enums\BookingStatus;
use App\Enums\CreativeStatus;
use App\Models\Creative;
use App\Models\Organisation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class CreativeRepository
{
    /** @return Collection<int, Creative> */
    public function forOrganisation(Organisation $org): Collection
    {
        return $org->creatives()->latest('id')->get();
    }

    public function findForOrganisation(Organisation $org, string $uuid): ?Creative
    {
        return $org->creatives()->where('uuid', $uuid)->first();
    }

    public function find(int $id): ?Creative
    {
        return Creative::find($id);
    }

    public function findByUuid(string $uuid): ?Creative
    {
        return Creative::with(['organisation', 'uploader'])->where('uuid', $uuid)->first();
    }

    public function create(Organisation $org, array $attributes): Creative
    {
        return $org->creatives()->create($attributes);
    }

    public function save(Creative $creative): Creative
    {
        $creative->save();

        return $creative;
    }

    public function delete(Creative $creative): void
    {
        $creative->delete();
    }

    public function hasActiveBookings(Creative $creative): bool
    {
        return $creative->bookings()->whereNotIn('status', [
            BookingStatus::Completed, BookingStatus::Rejected, BookingStatus::Cancelled,
        ])->exists();
    }

    public function paginateByStatus(?CreativeStatus $status, int $perPage = 20): LengthAwarePaginator
    {
        return Creative::with(['organisation', 'uploader'])
            ->when($status, fn ($q) => $q->where('status', $status))
            ->orderBy($status === CreativeStatus::InReview ? 'created_at' : 'updated_at', $status === CreativeStatus::InReview ? 'asc' : 'desc')
            ->paginate($perPage);
    }
}
