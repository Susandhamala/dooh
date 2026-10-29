<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'screen' => $this->whenLoaded('screen', fn () => ['id' => $this->screen->uuid, 'name' => $this->screen->name]),
            'slot_index' => $this->slot_index,
            'start_date' => $this->starts_on->toDateString(),
            'end_date' => $this->ends_on->toDateString(),
            'status' => $this->status->value,
            'price' => $this->price,
            'hold_expires_at' => $this->hold_expires_at?->toIso8601String(),
        ];
    }
}
