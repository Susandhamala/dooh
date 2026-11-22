<?php

namespace App\Repositories;

use App\Enums\WalletEntryType;
use App\Models\Organisation;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Carbon\CarbonImmutable;
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

    /**
     * Money across all wallets, in paisa: loaded by advertisers, paid for bookings (net of refunds),
     * and still sitting in balances.
     *
     * @return array{loaded: int, paid: int, balances: int}
     */
    public function totals(): array
    {
        $sums = WalletTransaction::query()->selectRaw('type, sum(amount_paisa) as total')->groupBy('type')->pluck('total', 'type');

        return [
            'loaded' => (int) ($sums['topup'] ?? 0),
            'paid' => -(int) ($sums['charge'] ?? 0) - (int) ($sums['refund'] ?? 0),
            'balances' => (int) Wallet::query()->sum('balance_paisa'),
        ];
    }

    /**
     * Booking money per day (paid minus refunded) for the last $days days, oldest first, Nepal time.
     *
     * @return array<string, int> date => paisa
     */
    public function dailyPaid(int $days, string $timezone): array
    {
        $from = CarbonImmutable::now($timezone)->startOfDay()->subDays($days - 1);
        $rows = WalletTransaction::query()
            ->whereIn('type', [WalletEntryType::Charge, WalletEntryType::Refund])
            ->where('created_at', '>=', $from->utc())
            ->selectRaw('(created_at AT TIME ZONE \'UTC\' AT TIME ZONE ?)::date as day, -sum(amount_paisa) as total', [$timezone])
            ->groupBy('day')
            ->pluck('total', 'day');

        $out = [];
        for ($i = 0; $i < $days; $i++) {
            $day = $from->addDays($i)->toDateString();
            $out[$day] = (int) ($rows[$day] ?? 0);
        }

        return $out;
    }
}
