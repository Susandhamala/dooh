<?php

namespace App\Models;

use App\Enums\WalletEntryType;
use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One line of the wallet ledger. Rows are only ever added, never changed. */
#[Fillable(['wallet_id', 'type', 'amount_paisa', 'balance_after_paisa', 'reserved_after_paisa', 'description', 'reference', 'user_id', 'created_at'])]
#[Hidden(['id', 'wallet_id', 'user_id'])]
class WalletTransaction extends Model
{
    use HasPublicUuid;

    public const UPDATED_AT = null;

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    protected function casts(): array
    {
        return [
            'type' => WalletEntryType::class,
            'amount_paisa' => 'integer',
            'balance_after_paisa' => 'integer',
            'reserved_after_paisa' => 'integer',
            'created_at' => 'immutable_datetime',
        ];
    }
}
