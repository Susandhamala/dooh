<?php

namespace App\Enums;

enum BookingStatus: string
{
    case Draft = 'draft';
    case Checking = 'checking';
    case AwaitingOwner = 'awaiting_owner';
    case Approved = 'approved';
    case Scheduled = 'scheduled';
    case Live = 'live';
    case Completed = 'completed';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    /** Statuses that no longer hold a slot (mirrors the exclusion constraint's WHERE clause). */
    public static function released(): array
    {
        return [self::Rejected, self::Cancelled];
    }

    public function isCancellable(): bool
    {
        return in_array($this, [self::Draft, self::Checking, self::AwaitingOwner, self::Approved, self::Scheduled], true);
    }
}
