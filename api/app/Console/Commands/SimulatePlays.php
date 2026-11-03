<?php

namespace App\Console\Commands;

use App\Services\BookingService;
use App\Services\PlayService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('plays:simulate')]
#[Description('Lab only: log and charge the plays live bookings should have had by now, as if screens reported them.')]
class SimulatePlays extends Command
{
    public function handle(BookingService $bookings, PlayService $plays): int
    {
        $now = CarbonImmutable::now();
        $bookings->advance($now);
        $this->info($plays->simulateDue($now).' play(s) recorded and charged.');

        return self::SUCCESS;
    }
}
