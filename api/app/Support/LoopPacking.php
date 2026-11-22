<?php

namespace App\Support;

/**
 * Where each ad sits in a screen's loop during one hour.
 *
 * Bookings don't own a fixed place: every hour, the ads booked for that hour are packed back to
 * back from the start of the loop, so there are never gaps between ads and all free time is one
 * block at the end. An ad booked to play n times a loop gets n places; first copies come first
 * (oldest booking first), then second copies, and so on, which spreads an ad's plays across the
 * loop when other ads are booked too.
 */
final class LoopPacking
{
    /**
     * @param  list<array{key: int|string, slots: int, repeats: int}>  $ads  in booking order (oldest first)
     * @return array<int|string, list<int>> key => first slot of each copy
     */
    public static function pack(array $ads): array
    {
        $out = [];
        $next = 0;
        $most = max([1, ...array_column($ads, 'repeats')]);
        for ($copy = 0; $copy < $most; $copy++) {
            foreach ($ads as $ad) {
                if ($copy < $ad['repeats']) {
                    $out[$ad['key']][] = $next;
                    $next += $ad['slots'];
                }
            }
        }

        return $out;
    }

    /** @param list<array{slots: int, repeats: int}> $ads */
    public static function used(array $ads): int
    {
        return array_sum(array_map(fn ($a) => $a['slots'] * $a['repeats'], $ads));
    }
}
