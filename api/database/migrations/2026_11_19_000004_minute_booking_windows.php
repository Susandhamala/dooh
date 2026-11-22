<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Booking windows to the minute instead of whole hours: [start_minute, end_minute) after midnight,
 * Nepal time. Existing bookings keep their hours (8 → 480).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE bookings DROP CONSTRAINT bookings_no_double_booking');
        DB::statement('ALTER TABLE bookings DROP COLUMN hour_range');
        DB::statement('ALTER TABLE bookings DROP CONSTRAINT bookings_hours_check');
        DB::statement('ALTER TABLE bookings RENAME COLUMN start_hour TO start_minute');
        DB::statement('ALTER TABLE bookings RENAME COLUMN end_hour TO end_minute');
        DB::statement('ALTER TABLE bookings ALTER COLUMN start_minute DROP DEFAULT, ALTER COLUMN end_minute DROP DEFAULT');
        DB::statement('UPDATE bookings SET start_minute = start_minute * 60, end_minute = end_minute * 60');
        DB::statement('ALTER TABLE bookings ADD CONSTRAINT bookings_minutes_check CHECK (start_minute < end_minute AND end_minute <= 1440)');
        DB::statement('ALTER TABLE bookings ADD COLUMN minute_range int4range GENERATED ALWAYS AS (int4range(start_minute, end_minute)) STORED');
        DB::statement(<<<'SQL'
            ALTER TABLE bookings ADD CONSTRAINT bookings_no_double_booking
            EXCLUDE USING gist (screen_id WITH =, slot_range WITH &&, date_range WITH &&, minute_range WITH &&)
            WHERE (status NOT IN ('rejected', 'cancelled'))
        SQL);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE bookings DROP CONSTRAINT bookings_no_double_booking');
        DB::statement('ALTER TABLE bookings DROP COLUMN minute_range');
        DB::statement('ALTER TABLE bookings DROP CONSTRAINT bookings_minutes_check');
        // Rounds out to whole hours.
        DB::statement('UPDATE bookings SET start_minute = start_minute / 60, end_minute = (end_minute + 59) / 60');
        DB::statement('ALTER TABLE bookings RENAME COLUMN start_minute TO start_hour');
        DB::statement('ALTER TABLE bookings RENAME COLUMN end_minute TO end_hour');
        DB::statement('ALTER TABLE bookings ADD CONSTRAINT bookings_hours_check CHECK (start_hour < end_hour AND end_hour <= 24)');
        DB::statement('ALTER TABLE bookings ADD COLUMN hour_range int4range GENERATED ALWAYS AS (int4range(start_hour, end_hour)) STORED');
        DB::statement(<<<'SQL'
            ALTER TABLE bookings ADD CONSTRAINT bookings_no_double_booking
            EXCLUDE USING gist (screen_id WITH =, slot_range WITH &&, date_range WITH &&, hour_range WITH &&)
            WHERE (status NOT IN ('rejected', 'cancelled'))
        SQL);
    }
};
