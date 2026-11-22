<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A one-time code that lets one new device enrol for a screen. */
#[Fillable(['screen_id', 'code_sha256', 'created_by', 'expires_at', 'used_at', 'created_at'])]
class DeviceEnrolmentCode extends Model
{
    public const UPDATED_AT = null;

    public function screen(): BelongsTo
    {
        return $this->belongsTo(Screen::class);
    }

    protected function casts(): array
    {
        return ['expires_at' => 'immutable_datetime', 'used_at' => 'immutable_datetime'];
    }
}
