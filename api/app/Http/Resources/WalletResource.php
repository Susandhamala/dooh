<?php

namespace App\Http\Resources;

use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WalletResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'balance' => Money::toRupees($this->balance_paisa),
            'reserved' => Money::toRupees($this->reserved_paisa),
            'available' => Money::toRupees($this->availablePaisa()),
            'min_topup' => config('dooh.wallet.min_topup'),
            'max_topup' => config('dooh.wallet.max_topup'),
            'gateways' => config('dooh.wallet.gateways'),
        ];
    }
}
