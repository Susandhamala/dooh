<?php

namespace App\Http\Resources;

use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $spent = $this->plays_done * Money::toPaisa($this->price_per_play);
        $holds = $this->status->holdsMoney();

        return [
            'id' => $this->uuid,
            'screen' => $this->whenLoaded('screen', fn () => [
                'id' => $this->screen->uuid,
                'name' => $this->screen->name,
                'address' => $this->screen->address,
            ]),
            'creative' => $this->whenLoaded('creative', fn () => $this->creative ? [
                'id' => $this->creative->uuid,
                'name' => $this->creative->name,
                'kind' => $this->creative->kind,
                'status' => $this->creative->status->value,
            ] : null),
            'start_date' => $this->starts_on->toDateString(),
            'end_date' => $this->ends_on->toDateString(),
            'start_hour' => $this->start_hour,
            'end_hour' => $this->end_hour,
            'status' => $this->status->value,
            'price_per_play' => $this->price_per_play,
            'plays_total' => $this->plays_total,
            'plays_done' => $this->plays_done,
            'price' => $this->price,
            'spent' => Money::toRupees($spent),
            // Still held in the wallet for plays to come; 0 once the booking is over.
            'held' => Money::toRupees($holds ? max(0, Money::toPaisa($this->price) - $spent) : 0),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
