<?php

namespace App\Repositories;

use App\Models\Booking;
use App\Models\PlayLog;
use Carbon\CarbonImmutable;

class PlayLogRepository
{
    /**
     * Store plays for $booking, skipping any already logged at the same time.
     *
     * @param  list<CarbonImmutable>  $times
     * @return int how many were new
     */
    public function insertNew(Booking $booking, array $times, string $source, string $charged): int
    {
        $now = now();
        $rows = array_map(fn (CarbonImmutable $t) => [
            'booking_id' => $booking->getKey(),
            'screen_id' => $booking->screen_id,
            'creative_id' => $booking->creative_id,
            'played_at' => $t->utc(),
            'source' => $source,
            'charged' => $charged,
            'created_at' => $now,
        ], $times);

        return array_sum(array_map(fn ($chunk) => PlayLog::insertOrIgnore($chunk), array_chunk($rows, 500)));
    }
}
