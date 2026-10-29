<?php

namespace App\Console\Commands;

use App\Services\AdminService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('dooh:make-admin {email : Email of an existing user}')]
#[Description('Grant the platform admin role to a user (they must then enrol two-factor).')]
class MakeAdmin extends Command
{
    public function handle(AdminService $admins): int
    {
        if (! $admins->grant($this->argument('email'))) {
            $this->error('No user with that email.');

            return self::FAILURE;
        }
        $this->info('Admin role granted.');

        return self::SUCCESS;
    }
}
