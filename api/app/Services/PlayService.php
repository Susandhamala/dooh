<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Repositories\BookingRepository;
use App\Repositories\PlayLogRepository;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Proof of play and per-play charging. Each logged play spends price_per_play from the booking's
 * hold. A play is logged once (unique per booking and time), so it can't be charged twice, and never
 * beyond the plays that were paid for.
 */
class PlayService
{
    public function __construct(
        private BookingRepository $bookings,
        private PlayLogRepository $plays,
        private WalletService $wallets,
    ) {}

    /**
     * @param  list<CarbonImmutable>  $times
     * @return int plays newly recorded and charged
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
            $this->wallets->charge(
                $b->organisation,
                $new * Money::toPaisa($b->price_per_play),
                $new === 1 ? "1 play on {$b->screen->name}" : "{$new} plays on {$b->screen->name}",
                $b->uuid,
            );

            return $new;
        });
    }

    /**
     * When play number $i (0-based) of a booking is due: the booking's slot in the loop, every loop,
     * through the booked hours of each booked day (Nepal time).
     */
    public function playTime(Booking $b, int $i): CarbonImmutable
    {
        $days = (int) $b->starts_on->diffInDays($b->ends_on) + 1;
        $perDay = intdiv($b->plays_total, $days);
        $perHour = max(1, intdiv($perDay, max(1, $b->end_hour - $b->start_hour)));
        $loop = intdiv(3600, $perHour);
        $rem = $i % $perDay;

        return CarbonImmutable::parse($b->starts_on->addDays(intdiv($i, $perDay))->toDateString(), config('dooh.timezone'))
            ->addHours($b->start_hour + intdiv($rem, $perHour))
            ->addSeconds(($rem % $perHour) * $loop + $b->slot_index * $b->screen->spot_seconds)
            ->utc();
    }

    /** Lab stand-in for real players: log and charge every play that should have happened by $now. */
    public function simulateDue(CarbonImmutable $now): int
    {
        $total = 0;
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
