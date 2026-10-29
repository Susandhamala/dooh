<?php

namespace App\Services;

use App\Repositories\UserRepository;

class AdminService
{
    public function __construct(private UserRepository $users) {}

    public function grant(string $email): bool
    {
        $user = $this->users->findByEmail($email);
        $user?->assignRole('admin');

        return $user !== null;
    }
}
