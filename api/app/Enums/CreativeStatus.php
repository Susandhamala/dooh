<?php

namespace App\Enums;

enum CreativeStatus: string
{
    /** File checks and re-encoding are running. */
    case Processing = 'processing';
    /** The file failed the automatic checks; check_result says why. */
    case Failed = 'failed';
    /** Waiting for the review team. */
    case InReview = 'in_review';
    case Approved = 'approved';
    case Rejected = 'rejected';

    /** Can be booked: approved, or still in review (the booking waits for the decision). */
    public function isBookable(): bool
    {
        return $this === self::InReview || $this === self::Approved;
    }
}
