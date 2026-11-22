<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Screen;
use App\Repositories\BookingRepository;
use App\Support\LoopPacking;
use App\Support\PlaySchedule;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * How full a screen's loop is, hour by hour, and where each booked ad plays in it.
 *
 * A booking takes slot_count × repeats slots of the loop in every hour its window touches, on each
 * of its days. Every hour the booked ads are packed back to back (LoopPacking), so a new ad fits
 * whenever enough slots are free in total, wherever earlier ads ended.
 */
class LoopService
{
    /** @var array<string, Collection<int, Booking>> bookings on one screen and day */
    private array $days = [];

    public function __construct(private BookingRepository $bookings) {}

    /**
     * Slots booked in each hour of each day in [$from, $to]: used[date][hour].
     *
     * @return array<string, array<int, int>>
     */
    public function usage(Screen $screen, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $used = [];
        foreach ($this->bookings->activeBetween($screen, $from, $to) as $b) {
            for ($d = $b->starts_on->max($from); $d->lte($b->ends_on->min($to)); $d = $d->addDay()) {
                foreach (self::hoursOf($b->start_minute, $b->end_minute) as $h) {
                    $used[$d->toDateString()][$h] = ($used[$d->toDateString()][$h] ?? 0) + $b->slot_count * $b->repeats;
                }
            }
        }

        return $used;
    }

    /**
     * The fewest free slots in any hour the window touches on any of the days: the most a new ad
     * (slots × repeats) can take there.
     */
    public function freeSlots(Screen $screen, CarbonImmutable $from, CarbonImmutable $to, int $startMinute, int $endMinute): int
    {
        $used = $this->usage($screen, $from, $to);
        $most = 0;
        for ($d = $from; $d->lte($to); $d = $d->addDay()) {
            foreach (self::hoursOf($startMinute, $endMinute) as $h) {
                $most = max($most, $used[$d->toDateString()][$h] ?? 0);
            }
        }

        return max(0, $screen->slotCount() - $most);
    }

    /**
     * Where each ad booked for $hour on $day plays: booking id => first slot of each copy.
     *
     * @return array<int, list<int>>
     */
    public function positions(Screen $screen, CarbonImmutable $day, int $hour): array
    {
        $ads = $this->onDay($screen, $day)
            ->filter(fn (Booking $b) => $b->start_minute < ($hour + 1) * 60 && $b->end_minute > $hour * 60)
            ->map(fn (Booking $b) => ['key' => $b->getKey(), 'slots' => $b->slot_count, 'repeats' => $b->repeats])
            ->values()->all();

        return LoopPacking::pack($ads);
    }

    /**
     * Seconds after midnight of every play of $booking on $day, in order.
     *
     * @return list<int>
     */
    public function playTimes(Booking $booking, CarbonImmutable $day): array
    {
        $screen = $booking->screen;
        $times = [];
        $hourPositions = [];
        foreach (PlaySchedule::loopStarts($screen->loop_seconds, $booking->start_minute, $booking->end_minute) as $start) {
            $h = intdiv($start, 3600);
            $hourPositions[$h] ??= $this->positions($screen, $day, $h)[$booking->getKey()] ?? [];
            foreach ($hourPositions[$h] as $slot) {
                $times[] = $start + $slot * $screen->spot_seconds;
            }
        }

        return $times;
    }

    /** Forget cached days (after bookings change). */
    public function forget(): void
    {
        $this->days = [];
    }

    /** @return list<int> hours that [start, end) minutes touch */
    public static function hoursOf(int $startMinute, int $endMinute): array
    {
        return $endMinute > $startMinute ? range(intdiv($startMinute, 60), intdiv($endMinute - 1, 60)) : [];
    }

    /** @return Collection<int, Booking> */
    private function onDay(Screen $screen, CarbonImmutable $day): Collection
    {
        return $this->days[$screen->getKey().'|'.$day->toDateString()] ??= $this->bookings->activeBetween($screen, $day, $day);
    }
}
