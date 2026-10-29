<?php

namespace App\Enums;

enum ScreenStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Paused = 'paused';
}
