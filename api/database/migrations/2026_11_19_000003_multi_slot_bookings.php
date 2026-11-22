<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Ads of any length: a booking takes `slot_count` back-to-back slots in the loop, starting at
 * `slot_index`. A 34 s video on a screen with 10 s slots takes 4 slots and pays for 4.
 * The double-booking guard now compares slot ranges instead of single slots.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE bookings ADD COLUMN slot_count smallint NOT NULL DEFAULT 1');
        DB::statement('ALTER TABLE bookings ADD CONSTRAINT bookings_slot_count_check CHECK (slot_count > 0)');
        // Half-open [slot_index, slot_index + slot_count), kept in step with the two columns.
        DB::statement('ALTER TABLE bookings ADD COLUMN slot_range int4range GENERATED ALWAYS AS (int4range(slot_index, slot_index + slot_count)) STORED');

        DB::statement('ALTER TABLE bookings DROP CONSTRAINT bookings_no_double_booking');
        DB::statement(<<<'SQL'
            ALTER TABLE bookings ADD CONSTRAINT bookings_no_double_booking
            EXCLUDE USING gist (screen_id WITH =, slot_range WITH &&, date_range WITH &&, hour_range WITH &&)
            WHERE (status NOT IN ('rejected', 'cancelled'))
        SQL);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE bookings DROP CONSTRAINT bookings_no_double_booking');
        DB::statement('ALTER TABLE bookings DROP COLUMN slot_range');
        DB::statement('ALTER TABLE bookings DROP CONSTRAINT bookings_slot_count_check');
        DB::statement('ALTER TABLE bookings DROP COLUMN slot_count');
        DB::statement(<<<'SQL'
            ALTER TABLE bookings ADD CONSTRAINT bookings_no_double_booking
            EXCLUDE USING gist (screen_id WITH =, slot_index WITH =, date_range WITH &&, hour_range WITH &&)
            WHERE (status NOT IN ('rejected', 'cancelled'))
        SQL);
    }
};
