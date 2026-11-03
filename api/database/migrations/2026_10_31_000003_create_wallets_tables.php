<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Money is whole paisa (integers) everywhere, so no rounding creeps in.
        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->unique()->constrained()->cascadeOnDelete();
            $table->bigInteger('balance_paisa')->default(0);
            // Part of the balance held for booked plays that haven't happened yet.
            $table->bigInteger('reserved_paisa')->default(0);
            $table->timestamps();
        });

        DB::statement('ALTER TABLE wallets ADD CONSTRAINT wallets_amounts_check CHECK (reserved_paisa >= 0 AND balance_paisa >= reserved_paisa)');

        // Append-only ledger: every change to a wallet, with the balance after it.
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('wallet_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20); // topup | hold | charge | release
            $table->bigInteger('amount_paisa'); // + adds to balance/reserve, - takes away
            $table->bigInteger('balance_after_paisa');
            $table->bigInteger('reserved_after_paisa');
            $table->string('description', 255);
            $table->string('reference', 100)->nullable(); // booking uuid or payment reference
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at');
            $table->index(['wallet_id', 'id']);
        });

        DB::statement("ALTER TABLE wallet_transactions ADD CONSTRAINT wallet_transactions_type_check CHECK (type IN ('topup', 'hold', 'charge', 'release'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
        Schema::dropIfExists('wallets');
    }
};
