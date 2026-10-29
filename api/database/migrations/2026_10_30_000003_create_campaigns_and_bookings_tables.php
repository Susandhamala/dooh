<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gist');

        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status', 20)->default('draft');
            $table->timestamps();
        });

        DB::statement('ALTER TABLE campaigns ADD CONSTRAINT campaigns_dates_check CHECK (end_date >= start_date)');

        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('screen_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('creative_id')->nullable(); // FK added with creatives in phase 2
            $table->unsignedSmallInteger('slot_index');
            $table->string('status', 20)->default('draft');
            $table->decimal('price', 12, 2);
            $table->timestamp('hold_expires_at')->nullable();
            $table->timestamps();
        });

        // Half-open [start, end + 1 day) so adjacent bookings don't overlap.
        DB::statement('ALTER TABLE bookings ADD COLUMN date_range daterange NOT NULL');
        DB::statement("ALTER TABLE bookings ADD CONSTRAINT bookings_status_check CHECK (status IN ('draft', 'checking', 'awaiting_owner', 'approved', 'scheduled', 'live', 'completed', 'rejected', 'cancelled'))");

        // The double-booking guard: one live booking per screen slot per day, enforced by the database.
        DB::statement(<<<'SQL'
            ALTER TABLE bookings ADD CONSTRAINT bookings_no_double_booking
            EXCLUDE USING gist (screen_id WITH =, slot_index WITH =, date_range WITH &&)
            WHERE (status NOT IN ('rejected', 'cancelled'))
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
        Schema::dropIfExists('campaigns');
    }
};
