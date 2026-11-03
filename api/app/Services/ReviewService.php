<?php

namespace App\Services;

use App\Enums\CreativeStatus;
use App\Enums\RejectReason;
use App\Enums\ReviewStatus;
use App\Exceptions\DomainError;
use App\Models\Creative;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The review team (the "ad agency") watches every ad before it can play. A named reviewer approves
 * it, or refuses it with a reason the advertiser sees. An approved ad can be pulled later the same way.
 */
class ReviewService
{
    public function __construct(
        private CreativeService $creatives,
        private BookingService $bookings,
        private DocumentService $documents,
    ) {}

    public function approve(User $reviewer, string $uuid, ?string $note): Creative
    {
        $creative = $this->creatives->findAny($uuid);
        if ($creative->status !== CreativeStatus::InReview) {
            throw DomainError::conflict('Only ads waiting for review can be approved.');
        }
        if (! $creative->uploader || $this->documents->status($creative->uploader) !== ReviewStatus::Approved->value) {
            throw DomainError::conflict("Verify the advertiser's identity document first.");
        }

        return DB::transaction(function () use ($creative, $reviewer, $note) {
            $creative->fill([
                'status' => CreativeStatus::Approved,
                'reviewed_by' => $reviewer->getKey(),
                'reviewed_at' => now(),
                'reject_reason' => null,
                'review_note' => $note,
            ]);
            $this->creatives->save($creative);
            $this->bookings->creativeApproved($creative);

            return $creative;
        });
    }

    public function reject(User $reviewer, string $uuid, RejectReason $reason, ?string $note): Creative
    {
        $creative = $this->creatives->findAny($uuid);
        if (! in_array($creative->status, [CreativeStatus::InReview, CreativeStatus::Approved], true)) {
            throw DomainError::conflict('This ad has already been refused or is not ready for review.');
        }

        return DB::transaction(function () use ($creative, $reviewer, $reason, $note) {
            $creative->fill([
                'status' => CreativeStatus::Rejected,
                'reviewed_by' => $reviewer->getKey(),
                'reviewed_at' => now(),
                'reject_reason' => $reason,
                'review_note' => $note,
            ]);
            $this->creatives->save($creative);
            $this->bookings->creativeRejected($creative);

            return $creative;
        });
    }
}
