<?php

namespace App\Services;

use App\Exceptions\DomainError;
use App\Models\User;
use App\Repositories\UserRepository;
use Illuminate\Support\Facades\Hash;
use PHPOpenSourceSaver\JWTAuth\JWTGuard;

class AuthService
{
    public function __construct(private UserRepository $users) {}

    /** Lab-only local sign-up; real users arrive through rentallnepal SSO. */
    public function register(array $data): array
    {
        $this->ensureLabLogin();

        if ($this->users->findByEmail($data['email'])) {
            throw new DomainError('The given data was invalid.', 422, ['email' => ['This email is already registered.']]);
        }

        $user = $this->users->create($data);

        return $this->issue($user);
    }

    /** Lab-only local login. */
    public function login(string $email, string $password): array
    {
        $this->ensureLabLogin();

        $user = $this->users->findByEmail($email);

        // Hash even when the user is missing so response time doesn't reveal which emails exist.
        $valid = Hash::check($password, $user?->password ?? '$argon2id$v=19$m=65536,t=4,p=1$c29tZXNhbHQ$RdescudvJCsgt3ub+b+dWRWJTmaaJObG');
        if (! $user || ! $user->password || ! $valid) {
            throw DomainError::unauthenticated('Invalid email or password.');
        }

        return $this->issue($user);
    }

    /**
     * Issue a token. `mfa` is true only after a TOTP check in this session; owner and admin
     * routes require it.
     */
    public function issue(User $user, bool $mfa = false): array
    {
        if (! $user->isActive()) {
            throw DomainError::forbidden('This account is suspended.');
        }

        $token = $this->guard()->claims(['mfa' => $mfa])->login($user);

        return [
            'token' => $token,
            'token_type' => 'bearer',
            'expires_in' => $this->guard()->factory()->getTTL() * 60,
            'mfa_verified' => $mfa,
            'mfa_required' => ! $mfa && $user->hasMfa(),
            'user' => $user->load('roles'),
        ];
    }

    public function refresh(): array
    {
        $token = $this->guard()->refresh();

        return [
            'token' => $token,
            'token_type' => 'bearer',
            'expires_in' => $this->guard()->factory()->getTTL() * 60,
        ];
    }

    public function logout(): void
    {
        $this->guard()->logout();
    }

    private function ensureLabLogin(): void
    {
        if (! config('dooh.lab_local_login')) {
            throw DomainError::forbidden('Local login is disabled. Sign in with rentallnepal.');
        }
    }

    private function guard(): JWTGuard
    {
        return auth('api');
    }
}
