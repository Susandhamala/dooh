<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Packed loops: a booking no longer owns a fixed place in the loop. It reserves `slot_count`
 * slots, `repeats` times a loop, for its days and time; every hour the screen packs the booked ads
 * back to back (LoopPacking), so gaps between ads merge into free time at the end of the loop.
 *
 * Capacity (booked slots ≤ slots per loop, in every hour) is checked while holding a lock on the
 * screen row, so two people paying at once can't overbook it. That replaces the exclusion
 * constraint, which can only compare fixed places.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE bookings DROP CONSTRAINT bookings_no_double_booking');
        DB::statement('ALTER TABLE bookings DROP COLUMN slot_range');
        DB::statement('ALTER TABLE bookings DROP COLUMN slot_index');
        DB::statement('ALTER TABLE bookings ADD COLUMN repeats smallint NOT NULL DEFAULT 1');
        DB::statement('ALTER TABLE bookings ADD CONSTRAINT bookings_repeats_check CHECK (repeats BETWEEN 1 AND 4)');
        DB::statement('CREATE INDEX bookings_screen_dates_index ON bookings USING gist (screen_id, date_range)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX bookings_screen_dates_index');
        DB::statement('ALTER TABLE bookings DROP CONSTRAINT bookings_repeats_check');
        DB::statement('ALTER TABLE bookings DROP COLUMN repeats');
        // Old fixed places can't be recovered; everything restarts at slot 0.
        DB::statement('ALTER TABLE bookings ADD COLUMN slot_index smallint NOT NULL DEFAULT 0');
        DB::statement('ALTER TABLE bookings ADD COLUMN slot_range int4range GENERATED ALWAYS AS (int4range(slot_index, slot_index + slot_count)) STORED');
    }
};
