<?php

namespace App\Services;

use App\Enums\OrganisationType;
use App\Models\Campaign;
use App\Models\User;
use App\Repositories\CampaignRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class CampaignService
{
    public function __construct(
        private CampaignRepository $campaigns,
        private OrganisationService $orgs,
    ) {}

    /** @return Collection<int, Campaign> */
    public function list(User $user, string $orgUuid): Collection
    {
        $membership = $this->orgs->membership($user, $orgUuid, OrganisationType::Advertiser);

        return $this->campaigns->forOrganisation($membership->organisation);
    }

    public function find(User $user, string $orgUuid, string $campaignUuid, bool $write = false): Campaign
    {
        $membership = $this->orgs->membership($user, $orgUuid, OrganisationType::Advertiser, $write);

        return $this->campaigns->findForOrganisation($membership->organisation, $campaignUuid)
            ?? throw (new ModelNotFoundException)->setModel(Campaign::class);
    }

    public function create(User $user, string $orgUuid, array $data): Campaign
    {
        $membership = $this->orgs->membership($user, $orgUuid, OrganisationType::Advertiser, write: true);

        return $this->campaigns->create($membership->organisation, $data);
    }
}
