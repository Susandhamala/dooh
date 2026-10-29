<?php

namespace App\Repositories;

use App\Models\Campaign;
use App\Models\Organisation;
use Illuminate\Database\Eloquent\Collection;

class CampaignRepository
{
    /** @return Collection<int, Campaign> */
    public function forOrganisation(Organisation $org): Collection
    {
        return $org->campaigns()->latest()->get();
    }

    public function findForOrganisation(Organisation $org, string $uuid): ?Campaign
    {
        return $org->campaigns()->where('uuid', $uuid)->first();
    }

    public function create(Organisation $org, array $attributes): Campaign
    {
        return $org->campaigns()->create($attributes);
    }
}
