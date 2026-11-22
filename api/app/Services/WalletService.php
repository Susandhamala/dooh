<?php

namespace App\Services;

use App\Enums\OrganisationType;
use App\Enums\WalletEntryType;
use App\Exceptions\DomainError;
use App\Models\Organisation;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Repositories\WalletRepository;
use App\Support\Money;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Prepaid wallets. Load money first; a booking is paid in full from the balance when it is made, and
 * plays that never happen (cancelled, ad pulled, screen off) are refunded.
 */
class WalletService
{
    public function __construct(private WalletRepository $wallets, private OrganisationService $orgs) {}

    public function show(User $user, string $orgUuid): Wallet
    {
        return $this->wallets->forOrganisation($this->advertiser($user, $orgUuid));
    }

    public function transactions(User $user, string $orgUuid): LengthAwarePaginator
    {
        return $this->wallets->transactions($this->show($user, $orgUuid));
    }

    /** Lab: the payment succeeds at once. A real gateway confirms through its callback first. */
    public function topUp(User $user, string $orgUuid, string $amount, string $gateway): WalletTransaction
    {
        $org = $this->orgs->membership($user, $orgUuid, OrganisationType::Advertiser, write: true)->organisation;
        $paisa = Money::toPaisa($amount);
        $names = ['esewa' => 'eSewa', 'khalti' => 'Khalti', 'bank' => 'bank transfer'];

        return DB::transaction(function () use ($org, $paisa, $gateway, $names, $user) {
            $wallet = $this->wallets->lockForOrganisation($org);

            return $this->wallets->apply(
                $wallet, WalletEntryType::TopUp, $paisa, 0, $paisa,
                'Money loaded via '.($names[$gateway] ?? $gateway),
                strtoupper($gateway).'-LAB-'.Str::upper(Str::random(10)),
                $user->getKey(),
            );
        });
    }

    /** Pay $paisa for a booking. Call inside the transaction that creates the booking. */
    public function pay(Organisation $org, int $paisa, string $description, string $reference, ?int $userId): void
    {
        $wallet = $this->wallets->lockForOrganisation($org);
        if ($wallet->availablePaisa() < $paisa) {
            $short = Money::toRupees($paisa - $wallet->availablePaisa());
            throw new DomainError("Not enough balance. Load at least रू {$short} more to book this.", 402, [
                'wallet' => ["You need रू {$short} more."],
            ]);
        }
        $this->wallets->apply($wallet, WalletEntryType::Charge, -$paisa, 0, -$paisa, $description, $reference, $userId);
    }

    /** Give back money for plays that will not happen. */
    public function refund(Organisation $org, int $paisa, string $description, string $reference, ?int $userId = null): void
    {
        if ($paisa <= 0) {
            return;
        }
        $wallet = $this->wallets->lockForOrganisation($org);
        $this->wallets->apply($wallet, WalletEntryType::Refund, $paisa, 0, $paisa, $description, $reference, $userId);
    }

    private function advertiser(User $user, string $orgUuid): Organisation
    {
        return $this->orgs->membership($user, $orgUuid, OrganisationType::Advertiser)->organisation;
    }
}
