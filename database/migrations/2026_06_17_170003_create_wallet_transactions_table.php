<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Immutable ledger. Every balance change writes one row here so the wallet is
 * fully auditable and can always be reconstructed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type');                          // deposit, withdraw, bet, payout, refund, bonus
            $table->decimal('amount', 18, 2);                // + credit, - debit
            $table->decimal('balance_after', 18, 2);         // balance snapshot after this entry
            $table->string('reference')->nullable();         // e.g. round:12, deposit:5, withdrawal:7
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
    }
};
