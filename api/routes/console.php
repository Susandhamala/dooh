<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('bookings:release-expired-holds')->everyMinute()->withoutOverlapping();

// Scheduled → live → completed (unused money back), by the clock.
Schedule::command('bookings:advance')->everyMinute()->withoutOverlapping();

// Lab only: fake proof-of-play until real screen players report it. Batched so the wallet ledger stays readable.
if (config('dooh.simulate_plays')) {
    Schedule::command('plays:simulate')->everyTenMinutes()->withoutOverlapping();
}
