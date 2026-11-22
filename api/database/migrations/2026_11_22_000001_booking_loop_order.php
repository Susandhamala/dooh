<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The screen owner can change the order ads play in the loop. Bookings without an order (null)
 * come after ordered ones, oldest first. The order never changes how much of the loop is used.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE bookings ADD COLUMN loop_order integer');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE bookings DROP COLUMN loop_order');
    }
};
