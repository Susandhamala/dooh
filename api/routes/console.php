<?php

use Illuminate\Support\Facades\Schedule;

// Scheduled → live → completed (missed plays refunded), by the clock.
Schedule::command('bookings:advance')->everyMinute()->withoutOverlapping();

// Sign a new playlist for each connected screen when its bookings change (needs the signing key).
Schedule::command('manifests:sign')->everyMinute()->withoutOverlapping();

// Lab only: fake proof-of-play until real screen players report it. Batched every 10 minutes.
if (config('dooh.simulate_plays')) {
    Schedule::command('plays:simulate')->everyTenMinutes()->withoutOverlapping();
}
