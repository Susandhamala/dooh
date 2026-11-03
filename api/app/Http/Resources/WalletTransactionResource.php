<?php

namespace App\Http\Resources;

use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WalletTransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'type' => $this->type->value,
            // + money in (top-up, released hold) / − money out (held or spent), from the available balance's view.
            'amount' => Money::toRupees($this->amount_paisa),
            'balance_after' => Money::toRupees($this->balance_after_paisa),
            'available_after' => Money::toRupees($this->balance_after_paisa - $this->reserved_after_paisa),
            'description' => $this->description,
            'reference' => $this->reference,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
