<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('creatives', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name', 150);
            $table->string('category', 30);
            $table->string('kind', 10); // image | video
            // The resolution the advertiser chose; the processed file is exactly this size.
            $table->unsignedSmallInteger('width_px');
            $table->unsignedSmallInteger('height_px');
            $table->decimal('duration_s', 6, 2)->nullable(); // videos only
            $table->string('original_path')->nullable(); // never served to screens
            $table->string('processed_path')->nullable();
            $table->string('thumb_path')->nullable();
            $table->string('mime', 60);
            $table->unsignedBigInteger('size_bytes');
            $table->char('sha256', 64)->nullable(); // of the processed file, for the signed manifest
            $table->string('status', 20)->default('processing');
            $table->json('check_result')->nullable();
            $table->timestamp('declared_at');
            $table->string('terms_version', 40);
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('reject_reason', 30)->nullable();
            $table->string('review_note', 500)->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });

        DB::statement("ALTER TABLE creatives ADD CONSTRAINT creatives_status_check CHECK (status IN ('processing', 'failed', 'in_review', 'approved', 'rejected'))");
        DB::statement("ALTER TABLE creatives ADD CONSTRAINT creatives_kind_check CHECK (kind IN ('image', 'video'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('creatives');
    }
};
