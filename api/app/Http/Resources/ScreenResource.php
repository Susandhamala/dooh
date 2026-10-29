<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ScreenResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'name' => $this->name,
            'description' => $this->description,
            'address' => $this->address,
            'lat' => $this->lat,
            'lng' => $this->lng,
            'width_px' => $this->width_px,
            'height_px' => $this->height_px,
            'orientation' => $this->orientation,
            'opens_at' => substr((string) $this->opens_at, 0, 5),
            'closes_at' => substr((string) $this->closes_at, 0, 5),
            'loop_seconds' => $this->loop_seconds,
            'spot_seconds' => $this->spot_seconds,
            'slots_per_loop' => $this->slotCount(),
            'slot_price' => $this->slot_price,
            'audience_notes' => $this->audience_notes,
            'content_rules' => $this->whenLoaded('contentRules', fn () => $this->contentRules->map(fn ($r) => $r->rule->value)->values()),
            'status' => $this->status->value,
        ];
    }
}
