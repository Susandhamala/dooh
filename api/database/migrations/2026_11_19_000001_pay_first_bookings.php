<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Pay first, first come first served: a booking is paid in full from the wallet when it is made,
 * and only approved ads can be booked. Nothing is "held" any more; unplayed plays are refunded.
 *
 * Existing data is moved over: bookings still waiting on ad review are cancelled and their hold is
 * released; every remaining hold is turned into a payment.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE wallet_transactions DROP CONSTRAINT wallet_transactions_type_check');
        DB::statement("ALTER TABLE wallet_transactions ADD CONSTRAINT wallet_transactions_type_check CHECK (type IN ('topup', 'hold', 'charge', 'release', 'refund'))");

        DB::transaction(function () {
            $now = now();

            $waiting = DB::table('bookings')
                ->where('status', 'checking')
                ->get(['id', 'uuid', 'organisation_id', 'price', 'price_per_play', 'plays_done']);
            foreach ($waiting as $b) {
                $left = (int) round($b->price * 100) - $b->plays_done * (int) round($b->price_per_play * 100);
                DB::table('bookings')->where('id', $b->id)->update(['status' => 'cancelled', 'updated_at' => $now]);
                if ($left > 0) {
                    $this->entry($b->organisation_id, 'release', 0, -$left, $left, 'Cancelled: only approved ads can be booked now', $b->uuid);
                }
            }

            foreach (DB::table('wallets')->where('reserved_paisa', '>', 0)->get(['organisation_id', 'reserved_paisa']) as $w) {
                $this->entry($w->organisation_id, 'charge', -$w->reserved_paisa, -$w->reserved_paisa, -$w->reserved_paisa, 'Bookings paid in full (held money moved to payment)', null);
            }
        });
    }

    public function down(): void
    {
        // Payments are not turned back into holds.
    }

    private function entry(int $orgId, string $type, int $balanceDelta, int $reservedDelta, int $amount, string $description, ?string $reference): void
    {
        $wallet = DB::table('wallets')->where('organisation_id', $orgId)->lockForUpdate()->first();
        $balance = $wallet->balance_paisa + $balanceDelta;
        $reserved = $wallet->reserved_paisa + $reservedDelta;
        DB::table('wallets')->where('id', $wallet->id)->update(['balance_paisa' => $balance, 'reserved_paisa' => $reserved, 'updated_at' => now()]);
        DB::table('wallet_transactions')->insert([
            'uuid' => (string) Str::uuid7(),
            'wallet_id' => $wallet->id,
            'type' => $type,
            'amount_paisa' => $amount,
            'balance_after_paisa' => $balance,
            'reserved_after_paisa' => $reserved,
            'description' => $description,
            'reference' => $reference,
            'created_at' => now(),
        ]);
    }
};
