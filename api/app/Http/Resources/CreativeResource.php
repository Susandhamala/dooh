<?php

namespace App\Http\Resources;

use App\Enums\CreativeStatus;
use App\Support\NepaliPhone;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\URL;

/** An ad. Media links are signed and expire, so a copied link stops working. */
class CreativeResource extends JsonResource
{
    public bool $forReviewer = false;

    /** Reviewers also see who uploaded it and whether their identity is verified. */
    public function reviewer(): static
    {
        $this->forReviewer = true;

        return $this;
    }

    public function toArray(Request $request): array
    {
        $ready = $this->processed_path !== null;
        $url = fn (string $variant) => URL::temporarySignedRoute(
            'media.creative', now()->addMinutes(30), ['creative' => $this->uuid, 'variant' => $variant], absolute: false,
        );

        $data = [
            'id' => $this->uuid,
            'name' => $this->name,
            'category' => $this->category->value,
            'kind' => $this->kind,
            'width_px' => $this->width_px,
            'height_px' => $this->height_px,
            'orientation' => $this->orientation(),
            'duration_s' => $this->duration_s,
            'size_bytes' => $this->size_bytes,
            'status' => $this->status->value,
            'check' => [
                'errors' => $this->check_result['errors'] ?? [],
                'notes' => $this->check_result['notes'] ?? [],
            ],
            'reject_reason' => $this->reject_reason?->value,
            'reject_label' => $this->reject_reason?->label(),
            'review_note' => $this->review_note,
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'media_url' => $ready ? $url('media') : null,
            'thumb_url' => $ready && $this->thumb_path ? $url('thumb') : null,
            'created_at' => $this->created_at?->toIso8601String(),
        ];

        if ($this->forReviewer) {
            $data['organisation'] = ['id' => $this->organisation->uuid, 'name' => $this->organisation->name];
            $data['uploader'] = $this->uploader ? [
                'name' => $this->uploader->name,
                'phone' => $this->uploader->phone ? NepaliPhone::mask($this->uploader->phone) : null,
                'document_status' => $this->uploader->identityDocuments()->latest('id')->value('status') ?? 'none',
            ] : null;
            $data['declared_at'] = $this->declared_at?->toIso8601String();
            $data['sha256'] = $this->sha256;
            $data['can_approve'] = $this->status === CreativeStatus::InReview;
        }

        return $data;
    }
}
