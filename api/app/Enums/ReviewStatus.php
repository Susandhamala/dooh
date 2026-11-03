<?php

namespace App\Enums;

/** Where an identity document stands with the review team. */
enum ReviewStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
