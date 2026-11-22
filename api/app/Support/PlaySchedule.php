<?php

namespace App\Support;

/**
 * When an ad plays inside a booked time window.
 *
 * A screen runs a loop of `loop` seconds that restarts at the top of every hour (Nepal time). An ad
 * plays once (or as many times as booked) in every loop that starts inside its window
 * [startMinute, endMinute). Where in the loop it plays is set by LoopPacking. The portal mirrors
 * this in portal/src/lib/schedule.ts.
 */
final class PlaySchedule
{
    /**
     * Seconds after midnight at which a loop starts inside the window.
     *
     * @return list<int>
     */
    public static function loopStarts(int $loop, int $startMinute, int $endMinute): array
    {
        $from = $startMinute * 60;
        $to = $endMinute * 60;
        $loops = intdiv(3600, $loop);
        $times = [];
        for ($h = intdiv($startMinute, 60); $h * 3600 < $to; $h++) {
            for ($k = 0; $k < $loops; $k++) {
                $t = $h * 3600 + $k * $loop;
                if ($t >= $from && $t < $to) {
                    $times[] = $t;
                }
            }
        }

        return $times;
    }

    /** Minutes after midnight for "HH:MM" ("24:00" allowed). */
    public static function minutes(string $time): int
    {
        [$h, $m] = array_map('intval', explode(':', substr($time, 0, 5)));

        return $h * 60 + $m;
    }

    /** "HH:MM" for minutes after midnight. */
    public static function time(int $minutes): string
    {
        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }

    /** "HH:MM:SS" for seconds after midnight. */
    public static function clock(int $seconds): string
    {
        return sprintf('%02d:%02d:%02d', intdiv($seconds, 3600), intdiv($seconds % 3600, 60), $seconds % 60);
    }
}
