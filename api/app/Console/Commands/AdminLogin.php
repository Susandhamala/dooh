<?php

namespace App\Console\Commands;

use App\Services\AdminService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('dooh:admin-login {username : Username for the /4dmin sign-in} {--name= : Display name (new accounts)} {--password= : Password; asked for if left out}')]
#[Description('Create an admin who signs in on /4dmin with a username and password, or change their password.')]
class AdminLogin extends Command
{
    public function handle(AdminService $admins): int
    {
        $password = (string) ($this->option('password') ?? $this->secret('Password'));
        if (mb_strlen($password) < 6) {
            $this->error('Use a password of at least 6 characters.');

            return self::FAILURE;
        }

        $created = $admins->setPasswordLogin((string) $this->argument('username'), $password, $this->option('name'));
        $this->info($created ? 'Admin created. Sign in on /4dmin.' : 'Password changed.');

        return self::SUCCESS;
    }
}
