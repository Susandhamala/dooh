<?php

namespace App\Repositories;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Creative;
use App\Models\Organisation;
use App\Models\Screen;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class BookingRepository
{
    /** @return Collection<int, Booking> */
    public function forOrganisation(Organisation $org): Collection
    {
        return $org->bookings()->with(['screen', 'creative'])->latest('id')->get();
    }

    public function findForOrganisation(Organisation $org, string $uuid): ?Booking
    {
        return $org->bookings()->with(['screen', 'creative'])->where('uuid', $uuid)->first();
    }

    /** The booking row locked for the rest of the transaction (status and play counters change). */
    public function lock(Booking $booking): Booking
    {
        return Booking::with(['screen', 'creative', 'organisation'])->whereKey($booking->getKey())->lockForUpdate()->firstOrFail();
    }

    /**
     * Bookings on $screen that hold loop time on any day in [$start, $end], oldest first (the order
     * the loop is packed in).
     *
     * @return Collection<int, Booking>
     */
    public function activeBetween(Screen $screen, CarbonImmutable $start, CarbonImmutable $end): Collection
    {
        return $this->active($screen)
            ->whereRaw('date_range && ?::daterange', [Booking::dateRange($start, $end)])
            ->orderByRaw('loop_order NULLS LAST, id')
            ->get(['id', 'date_range', 'start_minute', 'end_minute', 'slot_count', 'repeats', 'loop_order']);
    }

    /**
     * Bookings on a screen for one day, with the ad and the advertiser, for the owner's schedule.
     *
     * @return Collection<int, Booking>
     */
    public function onScreenOn(Screen $screen, CarbonImmutable $day): Collection
    {
        return $this->active($screen)
            ->whereRaw('date_range @> ?::date', [$day->toDateString()])
            ->with(['creative', 'organisation', 'bookedBy'])
            ->orderByRaw('loop_order NULLS LAST, id')
            ->get();
    }

    /**
     * Bookings on a screen waiting for the owner, oldest first.
     *
     * @return Collection<int, Booking>
     */
    public function awaitingOn(Screen $screen): Collection
    {
        return $this->active($screen)
            ->where('status', BookingStatus::AwaitingOwner)
            ->with(['creative', 'organisation', 'bookedBy'])
            ->orderBy('id')
            ->get();
    }

    public function findOnScreen(Screen $screen, string $uuid): ?Booking
    {
        return $this->active($screen)->where('uuid', $uuid)->first();
    }

    /**
     * Every booking on the screen that still plays (today or later), in loop order.
     *
     * @return Collection<int, Booking>
     */
    public function stillPlaying(Screen $screen, CarbonImmutable $today): Collection
    {
        return $this->active($screen)
            ->where('status', '!=', BookingStatus::Completed)
            ->whereRaw('upper(date_range) > ?::date', [$today->toDateString()])
            ->orderByRaw('loop_order NULLS LAST, id')
            ->get();
    }

    /** Totals for an owner's screens: bookings running, upcoming, and money from plays done. */
    public function ownerTotals(array $screenIds, CarbonImmutable $today): array
    {
        $q = fn () => Booking::query()->whereIn('screen_id', $screenIds);

        return [
            'waiting' => $q()->where('status', BookingStatus::AwaitingOwner)->count(),
            'live' => $q()->where('status', BookingStatus::Live)->count(),
            'upcoming' => $q()->where('status', BookingStatus::Scheduled)->count(),
            'today' => $q()->whereIn('status', [BookingStatus::AwaitingOwner, BookingStatus::Scheduled, BookingStatus::Live])->whereRaw('date_range @> ?::date', [$today->toDateString()])->count(),
            'earned' => (string) ($q()->selectRaw('coalesce(sum(plays_done * price_per_play), 0) as s')->value('s') ?? 0),
        ];
    }

    public function create(array $attributes): Booking
    {
        return Booking::create($attributes);
    }

    public function save(Booking $booking): Booking
    {
        $booking->save();

        return $booking;
    }

    /** @return Collection<int, Booking> */
    public function openForCreative(Creative $creative): Collection
    {
        return $creative->bookings()->whereIn('status', [BookingStatus::AwaitingOwner, BookingStatus::Scheduled, BookingStatus::Live])->get();
    }

    /**
     * Bookings whose status may need to move on by $today: scheduled or live, and starting no
     * later than $today.
     *
     * @return Collection<int, Booking>
     */
    public function dueForUpdate(CarbonImmutable $today): Collection
    {
        return Booking::query()
            ->whereIn('status', [BookingStatus::AwaitingOwner, BookingStatus::Scheduled, BookingStatus::Live])
            ->whereRaw('lower(date_range) <= ?::date', [$today->toDateString()])
            ->get();
    }

    /** @return Collection<int, Booking> */
    public function live(): Collection
    {
        return Booking::query()->where('status', BookingStatus::Live)->get();
    }

    public function findByUuid(string $uuid): ?Booking
    {
        return Booking::with(['screen', 'creative', 'organisation'])->where('uuid', $uuid)->first();
    }

    /** Every booking, newest first, for the admin panel. Search matches the screen or the advertiser. */
    public function paginateForAdmin(?string $status, ?string $search, int $perPage = 25): LengthAwarePaginator
    {
        return Booking::query()
            ->with(['screen', 'creative', 'organisation'])
            ->when($status === 'active', fn ($q) => $q->whereIn('status', [BookingStatus::AwaitingOwner, BookingStatus::Scheduled, BookingStatus::Live]))
            ->when($status && $status !== 'active', fn ($q) => $q->where('status', $status))
            ->when($search, fn ($q) => $q->where(fn ($w) => $w
                ->whereHas('screen', fn ($s) => $s->where('name', 'ilike', "%{$search}%"))
                ->orWhereHas('organisation', fn ($o) => $o->where('name', 'ilike', "%{$search}%"))))
            ->latest('id')
            ->paginate($perPage);
    }

    /** @return array<string, int> status => count */
    public function countByStatus(): array
    {
        return Booking::query()->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status')->map(fn ($n) => (int) $n)->all();
    }

    /** @return Collection<int, Booking> */
    public function latest(int $n): Collection
    {
        return Booking::with(['screen', 'creative', 'organisation'])->latest('id')->limit($n)->get();
    }

    private function active(Screen $screen): Builder
    {
        return Booking::query()
            ->where('screen_id', $screen->getKey())
            ->whereNotIn('status', BookingStatus::released());
    }
}
