<?php

namespace App\Services;

use App\Repositories\UserRepository;
use App\Support\NepaliPhone;

class AdminService
{
    public function __construct(private UserRepository $users) {}

    /** Grant the admin role to the user with this email or Nepali mobile number. */
    public function grant(string $emailOrPhone): bool
    {
        $phone = NepaliPhone::normalise($emailOrPhone);
        $user = $phone ? $this->users->findByPhone($phone) : $this->users->findByEmail($emailOrPhone);
        $user?->assignRole('admin');

        return $user !== null;
    }
}
