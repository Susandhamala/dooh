<?php

namespace App\Enums;

/** Content an owner can refuse on a screen; checked during Check in phase 2. */
enum ContentRule: string
{
    case NoAlcohol = 'no_alcohol';
    case NoTobacco = 'no_tobacco';
    case NoPolitical = 'no_political';
    case NoGambling = 'no_gambling';
    case NoReligious = 'no_religious';
    case NoAdult = 'no_adult';
}
