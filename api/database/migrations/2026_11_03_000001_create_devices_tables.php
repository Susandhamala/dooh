<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A screen's player (Raspberry Pi or emulated). It proves who it is with a client certificate.
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('screen_id')->constrained()->cascadeOnDelete();
            $table->string('cert_serial', 64);
            // SHA-1 hex of the certificate, the form Nginx passes as $ssl_client_fingerprint.
            $table->char('cert_fingerprint', 40)->unique();
            $table->timestamp('cert_expires_at');
            $table->foreignId('enrolled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('enrolled_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->string('last_ip', 45)->nullable();
            $table->string('player_version', 40)->nullable();
            $table->timestamps();
        });
        DB::statement('CREATE UNIQUE INDEX devices_one_active_per_screen ON devices (screen_id) WHERE revoked_at IS NULL');

        // One-time codes an owner types on a new device. Only the hash is stored.
        Schema::create('device_enrolment_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('screen_id')->constrained()->cascadeOnDelete();
            $table->char('code_sha256', 64)->unique();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamp('created_at');
        });

        // The latest signed manifest per screen, written by the signer and served as-is to the device.
        Schema::create('screen_manifests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('screen_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('version'); // never lower than the signing time, so it keeps rising after a database reset
            $table->char('content_sha256', 64);
            $table->text('payload');
            $table->string('signature', 100);
            $table->timestamp('signed_at');
            $table->timestamp('expires_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('screen_manifests');
        Schema::dropIfExists('device_enrolment_codes');
        Schema::dropIfExists('devices');
    }
};
