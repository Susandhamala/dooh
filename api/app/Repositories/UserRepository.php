<?php

namespace App\Repositories;

use App\Models\TermsAcceptance;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class UserRepository
{
    public function findByEmail(string $email): ?User
    {
        return User::where('email', mb_strtolower($email))->first();
    }

    public function findByUsername(string $username): ?User
    {
        return User::where('username', mb_strtolower(trim($username)))->first();
    }

    public function findByPhone(string $e164): ?User
    {
        return User::where('phone', $e164)->first();
    }

    public function findByRentallUuid(string $uuid): ?User
    {
        return User::where('rentall_uuid', $uuid)->first();
    }

    public function create(array $attributes): User
    {
        if (isset($attributes['email'])) {
            $attributes['email'] = mb_strtolower($attributes['email']);
        }

        return User::create($attributes);
    }

    public function hasAcceptedTerms(User $user, string $version): bool
    {
        return $user->termsAcceptances()->where('version', $version)->exists();
    }

    public function recordTerms(User $user, array $attributes): TermsAcceptance
    {
        return $user->termsAcceptances()->firstOrCreate(['version' => $attributes['version']], $attributes);
    }

    public function findByUuid(string $uuid): ?User
    {
        return User::where('uuid', $uuid)->first();
    }

    /**
     * Everyone, newest first, with their latest document status, organisations, wallet and booking
     * count. $filter: a document status (none, pending, approved, rejected) or "suspended".
     */
    public function paginateForAdmin(?string $search, ?string $filter, int $perPage = 25): LengthAwarePaginator
    {
        $latestDoc = DB::table('identity_documents')->select('status')->whereColumn('user_id', 'users.id')->orderByDesc('id')->limit(1);

        return User::query()
            ->select('users.*')
            ->selectSub($latestDoc, 'document_status')
            ->with(['roles', 'organisations' => fn ($q) => $q->with('wallet')->withCount('bookings')])
            ->when($search, fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'ilike', "%{$search}%")
                ->orWhere('phone', 'like', '%'.preg_replace('/\D/', '', $search).'%')
                ->orWhere('email', 'ilike', "%{$search}%")))
            ->when($filter === 'suspended', fn ($q) => $q->where('status', User::STATUS_SUSPENDED))
            ->when($filter === 'none', fn ($q) => $q->whereNotExists(fn ($s) => $s->from('identity_documents')->whereColumn('user_id', 'users.id')))
            ->when(in_array($filter, ['pending', 'approved', 'rejected'], true), fn ($q) => $q->whereRaw('('.$latestDoc->toSql().') = ?', [...$latestDoc->getBindings(), $filter]))
            ->latest('id')
            ->paginate($perPage);
    }

    /** @return array{total: int, new_week: int, suspended: int} */
    public function counts(): array
    {
        return [
            'total' => User::count(),
            'new_week' => User::where('created_at', '>=', CarbonImmutable::now()->subDays(7))->count(),
            'suspended' => User::where('status', User::STATUS_SUSPENDED)->count(),
        ];
    }

    /** The newest $n users, with the same details as the admin list. */
    public function latest(int $n): Collection
    {
        return collect($this->paginateForAdmin(null, null, $n)->items());
    }

    public function save(User $user): User
    {
        $user->save();

        return $user;
    }

    /**
     * Store the TOTP time step only if it is newer than the last one, atomically,
     * so two concurrent requests can't both use the same code.
     */
    public function claimMfaTimestep(User $user, int $timestep): bool
    {
        $updated = User::whereKey($user->getKey())
            ->where(fn ($q) => $q->whereNull('mfa_last_timestep')->orWhere('mfa_last_timestep', '<', $timestep))
            ->update(['mfa_last_timestep' => $timestep]);

        return $updated === 1;
    }
}
