<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrganisationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'name' => $this->name,
            'type' => $this->type->value,
            'verified' => $this->isVerified(),
            'status' => $this->status,
            'my_role' => $this->whenPivotLoaded('memberships', fn () => $this->pivot->role),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
