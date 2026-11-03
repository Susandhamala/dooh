<?php

namespace App\Services;

use App\Exceptions\DomainError;
use App\Models\TermsAcceptance;
use App\Models\User;
use App\Repositories\UserRepository;

/** The terms and content policy, and each user's signed acceptance of them. */
class TermsService
{
    public function __construct(private UserRepository $users) {}

    /** The current terms, with the hash that an acceptance records. */
    public function current(): array
    {
        $terms = config('terms');

        return $terms + ['sha256' => $this->hash()];
    }

    public function version(): string
    {
        return (string) config('terms.version');
    }

    public function hasAccepted(User $user): bool
    {
        return $this->users->hasAcceptedTerms($user, $this->version());
    }

    public function accept(User $user, string $version, ?string $ip, ?string $userAgent): TermsAcceptance
    {
        if ($version !== $this->version()) {
            throw new DomainError('The terms have changed. Please read them again.', 409, ['version' => ['Outdated version.']]);
        }

        return $this->users->recordTerms($user, [
            'version' => $version,
            'text_sha256' => $this->hash(),
            'ip_address' => $ip,
            'user_agent' => $userAgent ? mb_substr($userAgent, 0, 255) : null,
            'accepted_at' => now(),
        ]);
    }

    private function hash(): string
    {
        return hash('sha256', (string) json_encode(config('terms'), JSON_UNESCAPED_UNICODE));
    }
}
