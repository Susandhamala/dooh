<?php

namespace App\Repositories;

use App\Enums\ScreenStatus;
use App\Models\Organisation;
use App\Models\Screen;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class ScreenRepository
{
    /** Screens advertisers can see: active, and owned by a verified, active organisation. */
    private function public(): Builder
    {
        return Screen::query()
            ->where('status', ScreenStatus::Active)
            ->whereHas('organisation', fn ($q) => $q->whereNotNull('verified_at')->where('status', 'active'));
    }

    public function paginatePublic(array $filters, int $perPage = 50): LengthAwarePaginator
    {
        return $this->public()
            ->with('contentRules')
            ->when($filters['orientation'] ?? null, fn ($q, $v) => $q->where('orientation', $v))
            ->when(isset($filters['bbox']), function ($q) use ($filters) {
                [$minLng, $minLat, $maxLng, $maxLat] = $filters['bbox'];
                $q->whereBetween('lat', [$minLat, $maxLat])->whereBetween('lng', [$minLng, $maxLng]);
            })
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function findPublic(string $uuid): ?Screen
    {
        return $this->public()->with('contentRules')->where('uuid', $uuid)->first();
    }

    /** Hold the screen row until the transaction ends, so bookings on it are checked one at a time. */
    public function lock(Screen $screen): void
    {
        Screen::query()->whereKey($screen->getKey())->lockForUpdate()->first(['id']);
    }

    /** @return Collection<int, Screen> */
    public function forOrganisation(Organisation $org): Collection
    {
        return $org->screens()->with(['contentRules', 'activeDevice'])->orderBy('name')->get();
    }

    public function findForOrganisation(Organisation $org, string $uuid): ?Screen
    {
        return $org->screens()->with('contentRules')->where('uuid', $uuid)->first();
    }

    public function create(Organisation $org, array $attributes): Screen
    {
        return $org->screens()->create($attributes);
    }

    public function update(Screen $screen, array $attributes): Screen
    {
        $screen->update($attributes);

        return $screen;
    }

    /** @return array<string, int> status => count */
    public function countByStatus(): array
    {
        return Screen::query()->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status')->map(fn ($n) => (int) $n)->all();
    }

    /** @param  list<string>  $rules */
    public function replaceContentRules(Screen $screen, array $rules): void
    {
        $screen->contentRules()->delete();
        $screen->contentRules()->createMany(array_map(fn ($rule) => ['rule' => $rule], array_values(array_unique($rules))));
        $screen->load('contentRules');
    }
}
