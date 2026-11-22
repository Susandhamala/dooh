<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** The latest signed playlist for a screen: base64 payload bytes and their Ed25519 signature. */
#[Fillable(['screen_id', 'version', 'content_sha256', 'payload', 'signature', 'signed_at', 'expires_at'])]
class ScreenManifest extends Model
{
    public function screen(): BelongsTo
    {
        return $this->belongsTo(Screen::class);
    }

    protected function casts(): array
    {
        return ['version' => 'integer', 'signed_at' => 'immutable_datetime', 'expires_at' => 'immutable_datetime'];
    }
}
