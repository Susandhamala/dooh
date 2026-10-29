<?php

namespace App\Repositories;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Campaign;
use App\Models\Screen;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class BookingRepository
{
    /** @return Collection<int, Booking> */
    public function forCampaign(Campaign $campaign): Collection
    {
        return $campaign->bookings()->with('screen')->latest()->get();
    }

    public function findForCampaign(Campaign $campaign, string $uuid): ?Booking
    {
        return $campaign->bookings()->with('screen')->where('uuid', $uuid)->first();
    }

    /**
     * Slot indexes on $screen already held for any day in [$start, $end].
     *
     * @return list<int>
     */
    public function takenSlots(Screen $screen, CarbonImmutable $start, CarbonImmutable $end): array
    {
        return $this->active($screen)
            ->whereRaw('date_range && ?::daterange', [Booking::dateRange($start, $end)])
            ->distinct()
            ->pluck('slot_index')
            ->all();
    }

    /**
     * Free slot count for each day in [$start, $end].
     *
     * @return array<string, int>
     */
    public function freeSlotsPerDay(Screen $screen, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $rows = DB::select(<<<'SQL'
            SELECT d::date AS day, COUNT(b.id) AS taken
            FROM generate_series(?::date, ?::date, interval '1 day') AS d
            LEFT JOIN bookings b
                ON b.screen_id = ? AND b.date_range @> d::date AND b.status NOT IN ('rejected', 'cancelled')
            GROUP BY d ORDER BY d
        SQL, [$start->toDateString(), $end->toDateString(), $screen->getKey()]);

        $slots = $screen->slotCount();
        $out = [];
        foreach ($rows as $row) {
            $out[$row->day] = max(0, $slots - (int) $row->taken);
        }

        return $out;
    }

    public function create(array $attributes): Booking
    {
        return Booking::create($attributes);
    }

    public function setStatus(Booking $booking, BookingStatus $status): Booking
    {
        $booking->update(['status' => $status, 'hold_expires_at' => null]);

        return $booking;
    }

    /** Cancel draft holds whose time ran out. Returns how many were released. */
    public function releaseExpiredHolds(CarbonImmutable $now): int
    {
        return Booking::query()
            ->where('status', BookingStatus::Draft)
            ->where('hold_expires_at', '<', $now)
            ->update(['status' => BookingStatus::Cancelled, 'hold_expires_at' => null, 'updated_at' => $now]);
    }

    private function active(Screen $screen)
    {
        return Booking::query()
            ->where('screen_id', $screen->getKey())
            ->whereNotIn('status', BookingStatus::released());
    }
}
