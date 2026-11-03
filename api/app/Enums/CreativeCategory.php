<?php

namespace App\Enums;

/** What an ad promotes. Screen owners' content rules refuse some categories. */
enum CreativeCategory: string
{
    case Retail = 'retail';
    case FoodDrink = 'food_drink';
    case Services = 'services';
    case Education = 'education';
    case Health = 'health';
    case RealEstate = 'real_estate';
    case Automotive = 'automotive';
    case Finance = 'finance';
    case Events = 'events';
    case Travel = 'travel';
    case Technology = 'technology';
    case Alcohol = 'alcohol';
    case Tobacco = 'tobacco';
    case Political = 'political';
    case Religious = 'religious';
    case Gambling = 'gambling';
    case PublicService = 'public_service';
    case Other = 'other';

    /** The owner content rule that refuses this category, if any. */
    public function blockedBy(): ?ContentRule
    {
        return match ($this) {
            self::Alcohol => ContentRule::NoAlcohol,
            self::Tobacco => ContentRule::NoTobacco,
            self::Political => ContentRule::NoPolitical,
            self::Religious => ContentRule::NoReligious,
            self::Gambling => ContentRule::NoGambling,
            default => null,
        };
    }
}
