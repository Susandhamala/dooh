<?php

namespace App\Repositories;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Creative;
use App\Models\Organisation;
use App\Models\Screen;
use Carbon\CarbonImmutable;
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
     * Slot indexes on $screen already taken on any day in [$start, $end] during any hour in
     * [$startHour, $endHour).
     *
     * @return list<int>
     */
    public function takenSlots(Screen $screen, CarbonImmutable $start, CarbonImmutable $end, int $startHour, int $endHour): array
    {
        return $this->active($screen)
            ->whereRaw('date_range && ?::daterange', [Booking::dateRange($start, $end)])
            ->whereRaw('hour_range && int4range(?, ?)', [$startHour, $endHour])
            ->distinct()
            ->pluck('slot_index')
            ->all();
    }

    /**
     * Bookings on $screen that touch [$start, $end], for building the hour-by-hour availability grid.
     *
     * @return Collection<int, Booking>
     */
    public function overlapping(Screen $screen, CarbonImmutable $start, CarbonImmutable $end): Collection
    {
        return $this->active($screen)
            ->whereRaw('date_range && ?::daterange', [Booking::dateRange($start, $end)])
            ->get(['id', 'date_range', 'start_hour', 'end_hour', 'slot_index']);
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
    public function waitingOnCreative(Creative $creative): Collection
    {
        return $creative->bookings()->where('status', BookingStatus::Checking)->get();
    }

    /** @return Collection<int, Booking> */
    public function openForCreative(Creative $creative): Collection
    {
        return $creative->bookings()->whereIn('status', [BookingStatus::Checking, BookingStatus::Scheduled, BookingStatus::Live])->get();
    }

    /**
     * Bookings whose status may need to move on by $today: scheduled or live, or still waiting
     * for review, and starting no later than $today.
     *
     * @return Collection<int, Booking>
     */
    public function dueForUpdate(CarbonImmutable $today): Collection
    {
        return Booking::query()
            ->whereIn('status', [BookingStatus::Checking, BookingStatus::Scheduled, BookingStatus::Live])
            ->whereRaw('lower(date_range) <= ?::date', [$today->toDateString()])
            ->get();
    }

    /** @return Collection<int, Booking> */
    public function live(): Collection
    {
        return Booking::query()->where('status', BookingStatus::Live)->get();
    }

    /** Cancel old-style draft holds whose time ran out. Returns how many were released. */
    public function releaseExpiredHolds(CarbonImmutable $now): int
    {
        return Booking::query()
            ->where('status', BookingStatus::Draft)
            ->where('hold_expires_at', '<', $now)
            ->update(['status' => BookingStatus::Cancelled, 'hold_expires_at' => null, 'updated_at' => $now]);
    }

    private function active(Screen $screen): Builder
    {
        return Booking::query()
            ->where('screen_id', $screen->getKey())
            ->whereNotIn('status', BookingStatus::released());
    }
}
