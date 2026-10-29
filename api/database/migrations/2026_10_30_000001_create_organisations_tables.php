<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organisations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('type', 20); // advertiser | owner
            $table->string('pan_number', 20)->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });

        Schema::create('memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->string('role', 20); // admin | editor | viewer
            $table->timestamps();
            $table->unique(['user_id', 'organisation_id']);
        });

        DB::statement("ALTER TABLE organisations ADD CONSTRAINT organisations_type_check CHECK (type IN ('advertiser', 'owner'))");
        DB::statement("ALTER TABLE memberships ADD CONSTRAINT memberships_role_check CHECK (role IN ('admin', 'editor', 'viewer'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('memberships');
        Schema::dropIfExists('organisations');
    }
};
