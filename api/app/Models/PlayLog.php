<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Proof of play: one row per time an ad was shown, and what it cost. */
#[Fillable(['booking_id', 'screen_id', 'creative_id', 'played_at', 'source', 'charged', 'created_at'])]
class PlayLog extends Model
{
    public const UPDATED_AT = null;

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    protected function casts(): array
    {
        return ['played_at' => 'immutable_datetime', 'charged' => 'decimal:2'];
    }
}
