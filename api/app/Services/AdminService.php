<?php

namespace App\Services;

use App\Exceptions\DomainError;
use App\Models\User;
use App\Repositories\BookingRepository;
use App\Repositories\CreativeRepository;
use App\Repositories\DocumentRepository;
use App\Repositories\ScreenRepository;
use App\Repositories\UserRepository;
use App\Repositories\WalletRepository;
use App\Support\Money;
use App\Support\NepaliPhone;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/** The admin panel: the numbers on the dashboard, and managing users. */
class AdminService
{
    public function __construct(
        private UserRepository $users,
        private DocumentRepository $documents,
        private CreativeRepository $creatives,
        private BookingRepository $bookings,
        private ScreenRepository $screens,
        private WalletRepository $wallets,
    ) {}

    /** Grant the admin role to the user with this email or Nepali mobile number. */
    public function grant(string $emailOrPhone): bool
    {
        $phone = NepaliPhone::normalise($emailOrPhone);
        $user = $phone ? $this->users->findByPhone($phone) : $this->users->findByEmail($emailOrPhone);
        $user?->assignRole('admin');

        return $user !== null;
    }

    /**
     * Create (or re-password) an admin who signs in on /4dmin with a username and password.
     * Returns true when a new account was made.
     */
    public function setPasswordLogin(string $username, string $password, ?string $name = null): bool
    {
        $username = mb_strtolower(trim($username));
        $user = $this->users->findByUsername($username);
        $created = $user === null;

        $user ??= $this->users->create(['name' => trim((string) ($name ?: $username)), 'username' => $username]);
        $user->password = $password;
        $this->users->save($user);
        $user->assignRole('admin');

        return $created;
    }

    /** Everything the dashboard shows, in one go. */
    public function overview(): array
    {
        $money = $this->wallets->totals();
        $daily = $this->wallets->dailyPaid(14, config('dooh.timezone'));

        return [
            'users' => $this->users->counts(),
            'documents' => $this->documents->countByStatus() + ['pending' => 0, 'approved' => 0, 'rejected' => 0],
            'ads' => $this->creatives->countByStatus() + ['processing' => 0, 'failed' => 0, 'in_review' => 0, 'approved' => 0, 'rejected' => 0],
            'bookings' => $this->bookings->countByStatus() + ['scheduled' => 0, 'live' => 0, 'completed' => 0, 'cancelled' => 0, 'rejected' => 0],
            'screens' => $this->screens->countByStatus() + ['draft' => 0, 'active' => 0, 'paused' => 0],
            'money' => array_map(fn (int $p) => Money::toRupees($p), $money),
            'daily' => array_map(fn ($day, $p) => ['date' => $day, 'amount' => Money::toRupees($p)], array_keys($daily), $daily),
            'recent_bookings' => $this->bookings->latest(6),
            'recent_users' => $this->users->latest(6),
        ];
    }

    public function users(?string $search, ?string $filter): LengthAwarePaginator
    {
        return $this->users->paginateForAdmin($search, $filter);
    }

    public function setSuspended(User $admin, string $uuid, bool $suspended): User
    {
        $user = $this->users->findByUuid($uuid) ?? throw (new ModelNotFoundException)->setModel(User::class);
        if ($suspended && ($user->is($admin) || $user->hasRole('admin'))) {
            throw DomainError::conflict('Admins can’t be suspended here. Remove the admin role first.');
        }
        $user->status = $suspended ? User::STATUS_SUSPENDED : User::STATUS_ACTIVE;

        return $this->users->save($user);
    }
}
