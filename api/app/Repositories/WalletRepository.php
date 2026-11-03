<?php

namespace App\Repositories;

use App\Enums\WalletEntryType;
use App\Models\Organisation;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class WalletRepository
{
    public function forOrganisation(Organisation $org): Wallet
    {
        return Wallet::firstOrCreate(['organisation_id' => $org->getKey()]);
    }

    /**
     * The wallet row locked until the surrounding transaction ends, so two bookings or plays
     * can't both spend the same rupee. Call inside DB::transaction().
     */
    public function lockForOrganisation(Organisation $org): Wallet
    {
        $wallet = $this->forOrganisation($org);

        return Wallet::whereKey($wallet->getKey())->lockForUpdate()->firstOrFail();
    }

    /**
     * Apply one ledger entry: change the wallet's balance and reserve, and append the matching
     * transaction row with the amounts after it.
     */
    public function apply(
        Wallet $wallet,
        WalletEntryType $type,
        int $balanceDelta,
        int $reservedDelta,
        int $amount,
        string $description,
        ?string $reference = null,
        ?int $userId = null,
    ): WalletTransaction {
        $wallet->balance_paisa += $balanceDelta;
        $wallet->reserved_paisa += $reservedDelta;
        $wallet->save();

        return $wallet->transactions()->create([
            'type' => $type,
            'amount_paisa' => $amount,
            'balance_after_paisa' => $wallet->balance_paisa,
            'reserved_after_paisa' => $wallet->reserved_paisa,
            'description' => $description,
            'reference' => $reference,
            'user_id' => $userId,
            'created_at' => now(),
        ]);
    }

    public function transactions(Wallet $wallet, int $perPage = 20): LengthAwarePaginator
    {
        return $wallet->transactions()->orderByDesc('id')->paginate($perPage);
    }
}
