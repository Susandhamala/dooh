<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** An advertiser organisation's prepaid balance, in paisa. */
#[Fillable(['organisation_id', 'balance_paisa', 'reserved_paisa'])]
#[Hidden(['id', 'organisation_id'])]
class Wallet extends Model
{
    protected $attributes = ['balance_paisa' => 0, 'reserved_paisa' => 0];

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class);
    }

    /** Money free for new bookings. */
    public function availablePaisa(): int
    {
        return $this->balance_paisa - $this->reserved_paisa;
    }

    protected function casts(): array
    {
        return ['balance_paisa' => 'integer', 'reserved_paisa' => 'integer'];
    }
}
