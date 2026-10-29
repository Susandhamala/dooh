<?php

namespace App\Services;

use App\Enums\OrganisationType;
use App\Exceptions\DomainError;
use App\Models\Membership;
use App\Models\Organisation;
use App\Models\User;
use App\Repositories\OrganisationRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class OrganisationService
{
    public function __construct(private OrganisationRepository $orgs) {}

    /** @return Collection<int, Organisation> */
    public function forUser(User $user): Collection
    {
        return $this->orgs->forUser($user);
    }

    public function create(User $user, array $data): Organisation
    {
        return DB::transaction(function () use ($user, $data) {
            $org = $this->orgs->create($data, $user);
            $user->assignRole(OrganisationType::from($data['type'])->platformRole());

            return $org;
        });
    }

    /**
     * Resolve the caller's membership in organisation $uuid.
     * Non-members, wrong organisation type and unknown IDs all give the same 404.
     */
    public function membership(User $user, string $uuid, ?OrganisationType $type = null, bool $write = false): Membership
    {
        $membership = $this->orgs->membership($user, $uuid);

        if (! $membership || ($type && $membership->organisation->type !== $type)) {
            throw (new ModelNotFoundException)->setModel(Organisation::class);
        }
        if ($membership->organisation->status !== 'active') {
            throw DomainError::forbidden('This organisation is suspended.');
        }
        if ($write && ! $membership->role->canWrite()) {
            throw DomainError::forbidden('Your role in this organisation is read-only.');
        }

        return $membership;
    }

    public function paginateForAdmin(?bool $verified): LengthAwarePaginator
    {
        return $this->orgs->paginateForAdmin($verified);
    }

    /** Mark an organisation verified. Document checks are simulated in the MVP. */
    public function verify(string $uuid): Organisation
    {
        $org = $this->find($uuid);
        $org->verified_at ??= now();

        return $this->orgs->save($org);
    }

    public function setSuspended(string $uuid, bool $suspended): Organisation
    {
        $org = $this->find($uuid);
        $org->status = $suspended ? 'suspended' : 'active';

        return $this->orgs->save($org);
    }

    private function find(string $uuid): Organisation
    {
        return $this->orgs->findByUuid($uuid) ?? throw (new ModelNotFoundException)->setModel(Organisation::class);
    }
}
