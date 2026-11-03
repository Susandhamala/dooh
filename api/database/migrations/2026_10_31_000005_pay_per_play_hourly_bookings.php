<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Screens are priced per play, not per day.
        Schema::table('screens', function (Blueprint $table) {
            $table->renameColumn('slot_price', 'price_per_play');
        });

        // Bookings belong to the advertiser organisation directly (campaigns become optional),
        // cover an hour window each day, and are paid per play from the wallet.
        Schema::table('bookings', function (Blueprint $table) {
            $table->foreignId('organisation_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('start_hour')->default(0);
            $table->unsignedSmallInteger('end_hour')->default(24);
            $table->decimal('price_per_play', 10, 2)->default(0);
            $table->unsignedInteger('plays_total')->default(0);
            $table->unsignedInteger('plays_done')->default(0);
        });

        DB::statement('UPDATE bookings SET organisation_id = campaigns.organisation_id FROM campaigns WHERE campaigns.id = bookings.campaign_id');
        DB::statement('ALTER TABLE bookings ALTER COLUMN organisation_id SET NOT NULL');
        DB::statement('ALTER TABLE bookings ALTER COLUMN campaign_id DROP NOT NULL');
        DB::statement('ALTER TABLE bookings ADD CONSTRAINT bookings_creative_fk FOREIGN KEY (creative_id) REFERENCES creatives (id) ON DELETE RESTRICT');
        DB::statement('ALTER TABLE bookings ADD CONSTRAINT bookings_hours_check CHECK (start_hour < end_hour AND end_hour <= 24)');
        DB::statement('ALTER TABLE bookings ADD CONSTRAINT bookings_plays_check CHECK (plays_done <= plays_total)');

        // Half-open hour window [start_hour, end_hour), kept in step with the two columns.
        DB::statement('ALTER TABLE bookings ADD COLUMN hour_range int4range GENERATED ALWAYS AS (int4range(start_hour, end_hour)) STORED');

        // The double-booking guard now also needs the hours to overlap.
        DB::statement('ALTER TABLE bookings DROP CONSTRAINT bookings_no_double_booking');
        DB::statement(<<<'SQL'
            ALTER TABLE bookings ADD CONSTRAINT bookings_no_double_booking
            EXCLUDE USING gist (screen_id WITH =, slot_index WITH =, date_range WITH &&, hour_range WITH &&)
            WHERE (status NOT IN ('rejected', 'cancelled'))
        SQL);

        // Proof of play. One row per play; the unique key stops the same play being charged twice.
        Schema::create('play_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('screen_id')->constrained()->cascadeOnDelete();
            $table->foreignId('creative_id')->constrained()->cascadeOnDelete();
            $table->timestamp('played_at');
            $table->string('source', 20); // device | simulated
            $table->decimal('charged', 10, 2);
            $table->timestamp('created_at');
            $table->unique(['booking_id', 'played_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('play_logs');
        DB::statement('ALTER TABLE bookings DROP CONSTRAINT bookings_no_double_booking');
        DB::statement('ALTER TABLE bookings DROP COLUMN hour_range');
        DB::statement('ALTER TABLE bookings DROP CONSTRAINT bookings_creative_fk');
        DB::statement('ALTER TABLE bookings DROP CONSTRAINT bookings_hours_check');
        DB::statement('ALTER TABLE bookings DROP CONSTRAINT bookings_plays_check');
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('organisation_id');
            $table->dropColumn(['start_hour', 'end_hour', 'price_per_play', 'plays_total', 'plays_done']);
        });
        DB::statement(<<<'SQL'
            ALTER TABLE bookings ADD CONSTRAINT bookings_no_double_booking
            EXCLUDE USING gist (screen_id WITH =, slot_index WITH =, date_range WITH &&)
            WHERE (status NOT IN ('rejected', 'cancelled'))
        SQL);
        Schema::table('screens', function (Blueprint $table) {
            $table->renameColumn('price_per_play', 'slot_price');
        });
    }
};
