<?php

namespace App\Services;

use App\Enums\OrganisationType;
use App\Models\Screen;
use App\Models\User;
use App\Repositories\BookingRepository;
use App\Repositories\ScreenRepository;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ScreenService
{
    public function __construct(
        private ScreenRepository $screens,
        private BookingRepository $bookings,
        private OrganisationService $orgs,
    ) {}

    public function catalogue(array $filters): LengthAwarePaginator
    {
        return $this->screens->paginatePublic($filters);
    }

    public function publicScreen(string $uuid): Screen
    {
        return $this->screens->findPublic($uuid) ?? throw (new ModelNotFoundException)->setModel(Screen::class);
    }

    /**
     * Free slots for every bookable hour of every day in [$from, $to]: `days[date][i]` is the free
     * count for `hours[i]`. A slot is taken in an hour when a booking covers that day and that hour.
     *
     * @return array{slots_per_loop: int, plays_per_hour: int, hours: list<int>, days: array<string, list<int>>}
     */
    public function availability(string $uuid, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $screen = $this->publicScreen($uuid);
        [$open, $close] = $screen->bookableHours();
        $hours = $open < $close ? range($open, $close - 1) : [];
        $slots = $screen->slotCount();

        $taken = [];
        foreach ($this->bookings->overlapping($screen, $from, $to) as $b) {
            $first = $b->starts_on->max($from);
            $last = $b->ends_on->min($to);
            for ($d = $first; $d->lte($last); $d = $d->addDay()) {
                for ($h = max($open, $b->start_hour); $h < min($close, $b->end_hour); $h++) {
                    $taken[$d->toDateString()][$h] = ($taken[$d->toDateString()][$h] ?? 0) + 1;
                }
            }
        }

        $days = [];
        for ($d = $from; $d->lte($to); $d = $d->addDay()) {
            $key = $d->toDateString();
            $days[$key] = array_map(fn ($h) => max(0, $slots - ($taken[$key][$h] ?? 0)), $hours);
        }

        return ['slots_per_loop' => $slots, 'plays_per_hour' => $screen->playsPerHour(), 'hours' => $hours, 'days' => $days];
    }

    /** @return Collection<int, Screen> */
    public function forOwner(User $user, string $orgUuid): Collection
    {
        $membership = $this->orgs->membership($user, $orgUuid, OrganisationType::Owner);

        return $this->screens->forOrganisation($membership->organisation);
    }

    public function ownerScreen(User $user, string $orgUuid, string $screenUuid): Screen
    {
        $membership = $this->orgs->membership($user, $orgUuid, OrganisationType::Owner);

        return $this->screens->findForOrganisation($membership->organisation, $screenUuid)
            ?? throw (new ModelNotFoundException)->setModel(Screen::class);
    }

    public function create(User $user, string $orgUuid, array $data): Screen
    {
        $membership = $this->orgs->membership($user, $orgUuid, OrganisationType::Owner, write: true);

        return DB::transaction(function () use ($membership, $data) {
            $screen = $this->screens->create($membership->organisation, collect($data)->except('content_rules')->all());
            $this->screens->replaceContentRules($screen, $data['content_rules'] ?? []);

            return $screen;
        });
    }

    public function update(User $user, string $orgUuid, string $screenUuid, array $data): Screen
    {
        $this->orgs->membership($user, $orgUuid, OrganisationType::Owner, write: true);
        $screen = $this->ownerScreen($user, $orgUuid, $screenUuid);

        return DB::transaction(function () use ($screen, $data) {
            $this->screens->update($screen, collect($data)->except('content_rules')->all());
            if (array_key_exists('content_rules', $data)) {
                $this->screens->replaceContentRules($screen, $data['content_rules']);
            }

            return $screen;
        });
    }
}
