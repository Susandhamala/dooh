<?php

namespace App\Http\Resources;

use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** A user as the admin panel lists them: who they are, verification, money and bookings. */
class AdminUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $orgs = $this->relationLoaded('organisations') ? $this->organisations : collect();

        return [
            'id' => $this->uuid,
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'status' => $this->status,
            'roles' => $this->getRoleNames()->values(),
            // none | pending | approved | rejected (latest identity document)
            'document_status' => $this->document_status ?? 'none',
            'organisations' => $orgs->map(fn ($o) => [
                'id' => $o->uuid,
                'name' => $o->name,
                'type' => $o->type->value ?? $o->type,
            ])->values(),
            'balance' => Money::toRupees((int) $orgs->sum(fn ($o) => $o->wallet?->balance_paisa ?? 0)),
            'bookings' => (int) $orgs->sum('bookings_count'),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
