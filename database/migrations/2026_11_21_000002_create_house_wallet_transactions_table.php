<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit ledger for every J-Pool / Admin-Profit movement.
 * Positive amount = credit, negative = debit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('house_wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('wallet_type');              // 'j_pool' | 'admin_profit'
            $table->decimal('amount', 18, 2);           // positive = credit, negative = debit
            $table->decimal('balance_after', 18, 2);
            $table->string('type');                     // 'j_payout' | 'profit_share' | 'manual'
            $table->string('reference')->nullable();    // e.g. 'round:42' | 'admin-topup'
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index('wallet_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('house_wallet_transactions');
    }
};
