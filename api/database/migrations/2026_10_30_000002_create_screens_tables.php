<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('screens', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('address')->nullable();
            $table->decimal('lat', 9, 6);
            $table->decimal('lng', 9, 6);
            $table->unsignedSmallInteger('width_px');
            $table->unsignedSmallInteger('height_px');
            $table->string('orientation', 10); // landscape | portrait
            $table->time('opens_at')->default('06:00');
            $table->time('closes_at')->default('22:00');
            // A loop of loop_seconds is split into loop_seconds / spot_seconds bookable slots.
            $table->unsignedSmallInteger('loop_seconds')->default(120);
            $table->unsignedSmallInteger('spot_seconds')->default(10);
            $table->decimal('slot_price', 10, 2); // NPR per slot per day
            $table->text('audience_notes')->nullable();
            $table->string('status', 20)->default('draft'); // draft | active | paused
            $table->timestamps();
            $table->index(['status']);
        });

        DB::statement('ALTER TABLE screens ADD CONSTRAINT screens_loop_check CHECK (spot_seconds > 0 AND loop_seconds >= spot_seconds AND loop_seconds % spot_seconds = 0)');
        DB::statement("ALTER TABLE screens ADD CONSTRAINT screens_orientation_check CHECK (orientation IN ('landscape', 'portrait'))");

        Schema::create('content_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('screen_id')->constrained()->cascadeOnDelete();
            $table->string('rule', 40);
            $table->timestamps();
            $table->unique(['screen_id', 'rule']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_rules');
        Schema::dropIfExists('screens');
    }
};
