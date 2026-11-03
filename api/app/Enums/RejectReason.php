<?php

namespace App\Enums;

/** Why the review team refused an ad. Shown to the advertiser with the reviewer's note. */
enum RejectReason: string
{
    case Sexual = 'sexual';
    case Violence = 'violence';
    case Weapons = 'weapons';
    case Harassment = 'harassment';
    case Illegal = 'illegal';
    case Misleading = 'misleading';
    case Copyright = 'copyright';
    case Quality = 'quality';
    case WrongCategory = 'wrong_category';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Sexual => 'Not permitted: sexual or pornographic content',
            self::Violence => 'Not permitted: violent or harmful content',
            self::Weapons => 'Not permitted: weapons or illegal goods',
            self::Harassment => 'Not permitted: harassment or hate',
            self::Illegal => 'Not permitted: illegal content',
            self::Misleading => 'Not permitted: misleading or fraudulent claims',
            self::Copyright => 'Not permitted: you may not have the rights to this content',
            self::Quality => 'Poor quality: blurry, cut off or hard to read',
            self::WrongCategory => 'Wrong category: choose the category that matches the ad',
            self::Other => 'Not approved',
        };
    }

    /** A policy breach (as opposed to a quality problem) counts against the account. */
    public function isViolation(): bool
    {
        return ! in_array($this, [self::Quality, self::WrongCategory, self::Other], true);
    }
}
