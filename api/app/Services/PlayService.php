<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Repositories\BookingRepository;
use App\Repositories\PlayLogRepository;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Proof of play. Bookings are paid up front; each logged play counts towards what was paid for, and
 * plays that never happen are refunded when the booking ends. A play is logged once (unique per
 * booking and time) and never beyond the plays that were paid for.
 */
class PlayService
{
    /** @var array<string, list<int>> a booking's play times on one day, for the binary search below */
    private array $times = [];

    public function __construct(
        private BookingRepository $bookings,
        private PlayLogRepository $plays,
        private LoopService $loops,
    ) {}

    /**
     * @param  list<CarbonImmutable>  $times
     * @return int plays newly recorded
     */
    public function record(Booking $booking, array $times, string $source): int
    {
        return DB::transaction(function () use ($booking, $times, $source) {
            $b = $this->bookings->lock($booking);
            if ($b->status !== BookingStatus::Live) {
                return 0;
            }
            usort($times, fn ($x, $y) => $x <=> $y);
            $times = array_slice($times, 0, max(0, $b->plays_total - $b->plays_done));
            if ($times === []) {
                return 0;
            }

            $new = $this->plays->insertNew($b, $times, $source, (string) $b->price_per_play);
            if ($new === 0) {
                return 0;
            }
            $b->plays_done += $new;
            $this->bookings->save($b);

            return $new;
        });
    }

    /**
     * When play number $i (0-based) of a booking is due: its packed place(s) in every loop that
     * starts inside the booked window, on each booked day (Nepal time).
     */
    public function playTime(Booking $b, int $i): CarbonImmutable
    {
        $days = (int) $b->starts_on->diffInDays($b->ends_on) + 1;
        $perDay = max(1, intdiv($b->plays_total, $days));
        $day = $b->starts_on->addDays(intdiv($i, $perDay));
        $times = $this->times[$b->getKey().'|'.$day->toDateString()] ??= $this->loops->playTimes($b, $day);
        // No place this hour (shouldn't happen): fall back to the start of its loop.
        $at = $times[$i % $perDay] ?? ($b->loopStarts()[intdiv($i % $perDay, max(1, $b->repeats))] ?? 0);

        return CarbonImmutable::parse($day->toDateString(), config('dooh.timezone'))->addSeconds($at)->utc();
    }

    /** Lab stand-in for real players: log every play that should have happened by $now. */
    public function simulateDue(CarbonImmutable $now): int
    {
        $total = 0;
        $this->times = [];
        $this->loops->forget();
        foreach ($this->bookings->live() as $booking) {
            $booking->load('screen');
            $due = $this->dueCount($booking, $now);
            if ($due <= $booking->plays_done) {
                continue;
            }
            $times = array_map(fn ($i) => $this->playTime($booking, $i), range($booking->plays_done, $due - 1));
            $total += $this->record($booking, $times, 'simulated');
        }

        return $total;
    }

    /** How many of the booking's plays are due by $now (play times only increase, so binary search). */
    private function dueCount(Booking $b, CarbonImmutable $now): int
    {
        [$lo, $hi] = [0, $b->plays_total];
        while ($lo < $hi) {
            $mid = intdiv($lo + $hi, 2);
            if ($this->playTime($b, $mid) <= $now) {
                $lo = $mid + 1;
            } else {
                $hi = $mid;
            }
        }

        return $lo;
    }
}
