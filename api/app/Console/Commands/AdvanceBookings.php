<?php

namespace App\Console\Commands;

use App\Services\BookingService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('bookings:advance')]
#[Description('Start bookings at their first booked hour and finish them after the last, releasing unused money.')]
class AdvanceBookings extends Command
{
    public function handle(BookingService $bookings): int
    {
        $done = $bookings->advance(CarbonImmutable::now());
        $this->info("{$done['live']} went live, {$done['completed']} completed, {$done['expired']} expired unapproved.");

        return self::SUCCESS;
    }
}
