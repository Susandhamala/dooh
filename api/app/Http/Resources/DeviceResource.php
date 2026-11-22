<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeviceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'online' => $this->isOnline(),
            'enrolled_at' => $this->enrolled_at?->toIso8601String(),
            'last_seen_at' => $this->last_seen_at?->toIso8601String(),
            'last_ip' => $this->last_ip,
            'player_version' => $this->player_version,
            'cert_expires_at' => $this->cert_expires_at?->toIso8601String(),
        ];
    }
}
