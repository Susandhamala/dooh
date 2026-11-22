<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A screen's player. Known only by its client certificate; revoked devices are refused at once. */
#[Fillable([
    'screen_id', 'cert_serial', 'cert_fingerprint', 'cert_expires_at', 'enrolled_by', 'enrolled_at',
    'revoked_at', 'last_seen_at', 'last_ip', 'player_version',
])]
#[Hidden(['id', 'screen_id', 'enrolled_by', 'cert_fingerprint'])]
class Device extends Model
{
    use HasPublicUuid;

    /** No contact for this long and the portal shows the screen as offline. */
    public const ONLINE_SECONDS = 180;

    public function screen(): BelongsTo
    {
        return $this->belongsTo(Screen::class);
    }

    public function isOnline(): bool
    {
        return $this->revoked_at === null && $this->last_seen_at?->gt(now()->subSeconds(self::ONLINE_SECONDS)) === true;
    }

    protected function casts(): array
    {
        return [
            'cert_expires_at' => 'immutable_datetime',
            'enrolled_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
            'last_seen_at' => 'immutable_datetime',
        ];
    }
}
