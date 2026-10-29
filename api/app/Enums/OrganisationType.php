<?php

namespace App\Enums;

enum OrganisationType: string
{
    case Advertiser = 'advertiser';
    case Owner = 'owner';

    /** Platform role a member of this kind of organisation gets. */
    public function platformRole(): string
    {
        return $this->value;
    }
}
