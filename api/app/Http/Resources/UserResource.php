<?php

namespace App\Http\Resources;

use App\Support\NepaliPhone;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'phone_masked' => $this->phone ? NepaliPhone::mask($this->phone) : null,
            'email_verified' => $this->email_verified_at !== null,
            'phone_verified' => $this->phone_verified_at !== null,
            'mfa_enabled' => $this->hasMfa(),
            'roles' => $this->getRoleNames()->values(),
            'terms_accepted' => $this->termsAcceptances()->where('version', config('terms.version'))->exists(),
            // none | pending | approved | rejected (latest identity document)
            'document_status' => $this->identityDocuments()->latest('id')->value('status') ?? 'none',
        ];
    }
}
