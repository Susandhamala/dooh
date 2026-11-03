<?php

namespace App\Services;

use App\Enums\OrganisationType;
use App\Exceptions\DomainError;
use App\Models\User;
use App\Repositories\UserRepository;
use App\Support\NepaliPhone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PHPOpenSourceSaver\JWTAuth\JWTGuard;

class AuthService
{
    public function __construct(
        private UserRepository $users,
        private PhoneOtpService $otp,
        private OrganisationService $orgs,
    ) {}

    /** Text a one-time code to a Nepali mobile number. */
    public function sendPhoneCode(string $phone): array
    {
        return $this->otp->send($this->phone($phone));
    }

    /**
     * Sign in with phone + code. A new number becomes a new account, which needs a name; it gets
     * its own advertiser organisation so it can load money and book straight away.
     */
    public function loginWithPhone(string $phoneInput, string $code, ?string $name): array
    {
        $phone = $this->phone($phoneInput);
        $user = $this->users->findByPhone($phone);

        if (! $user && blank($name)) {
            // Check the code first (without using it up) so the name step only appears for a real code.
            $this->otp->verify($phone, $code, consume: false);
            throw new DomainError('Tell us your name to finish creating your account.', 422, ['name' => ['Enter your name.']]);
        }

        $this->otp->verify($phone, $code);

        if (! $user) {
            $user = DB::transaction(function () use ($phone, $name) {
                $user = $this->users->create(['name' => trim((string) $name), 'phone' => $phone, 'phone_verified_at' => now()]);
                $this->orgs->create($user, ['name' => trim((string) $name), 'type' => OrganisationType::Advertiser->value]);

                return $user;
            });
        } elseif (! $user->phone_verified_at) {
            $user->phone_verified_at = now();
            $this->users->save($user);
        }

        return $this->issue($user);
    }

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

    private function phone(string $input): string
    {
        return NepaliPhone::normalise($input)
            ?? throw new DomainError('Enter a valid Nepali mobile number.', 422, ['phone' => ['Enter a valid Nepali mobile number, like 98XXXXXXXX.']]);
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
