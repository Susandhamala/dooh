<?php

namespace App\Console\Commands;

use App\Services\BookingService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('bookings:release-expired-holds')]
#[Description('Cancel draft bookings whose hold has expired, freeing their slots.')]
class ReleaseExpiredHolds extends Command
{
    public function handle(BookingService $bookings): int
    {
        $this->info($bookings->releaseExpiredHolds().' hold(s) released.');

        return self::SUCCESS;
    }
}
