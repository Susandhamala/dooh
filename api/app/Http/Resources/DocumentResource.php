<?php

namespace App\Http\Resources;

use App\Support\NepaliPhone;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\URL;

/** An identity document. Reviewers (`$forReviewer`) also get the owner and a short-lived link to the file. */
class DocumentResource extends JsonResource
{
    public bool $forReviewer = false;

    public function reviewer(): static
    {
        $this->forReviewer = true;

        return $this;
    }

    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->uuid,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'number' => $this->maskedNumber(),
            'status' => $this->status->value,
            'review_note' => $this->review_note,
            'submitted_at' => $this->created_at?->toIso8601String(),
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
        ];

        if ($this->forReviewer) {
            $data['user'] = [
                'id' => $this->user->uuid,
                'name' => $this->user->name,
                'phone' => $this->user->phone ? NepaliPhone::mask($this->user->phone) : null,
                'email' => $this->user->email,
            ];
            $data['mime'] = $this->mime;
            $data['file_url'] = $this->file_path
                ? URL::temporarySignedRoute('media.document', now()->addMinutes(10), ['document' => $this->uuid], absolute: false)
                : null;
        }

        return $data;
    }
}
