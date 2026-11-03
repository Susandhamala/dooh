<?php

namespace App\Models;

use App\Enums\CreativeCategory;
use App\Enums\CreativeStatus;
use App\Enums\RejectReason;
use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** An uploaded ad. Only the processed file is ever served or played. */
#[Fillable([
    'organisation_id', 'uploaded_by', 'name', 'category', 'kind', 'width_px', 'height_px', 'duration_s',
    'original_path', 'processed_path', 'thumb_path', 'mime', 'size_bytes', 'sha256', 'status', 'check_result',
    'declared_at', 'terms_version', 'reviewed_by', 'reviewed_at', 'reject_reason', 'review_note',
])]
#[Hidden(['id', 'organisation_id', 'uploaded_by', 'reviewed_by', 'original_path', 'processed_path', 'thumb_path'])]
class Creative extends Model
{
    use HasFactory, HasPublicUuid;

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function orientation(): string
    {
        return $this->height_px > $this->width_px ? 'portrait' : 'landscape';
    }

    /** Same shape as the screen (1% tolerance), so it fills the screen without bars. */
    public function fits(Screen $screen): bool
    {
        return abs($this->width_px / $this->height_px - $screen->width_px / $screen->height_px) < 0.01;
    }

    protected function casts(): array
    {
        return [
            'status' => CreativeStatus::class,
            'category' => CreativeCategory::class,
            'reject_reason' => RejectReason::class,
            'check_result' => 'array',
            'duration_s' => 'float',
            'declared_at' => 'immutable_datetime',
            'reviewed_at' => 'immutable_datetime',
        ];
    }
}
