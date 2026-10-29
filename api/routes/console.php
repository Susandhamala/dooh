<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('bookings:release-expired-holds')->everyMinute()->withoutOverlapping();
