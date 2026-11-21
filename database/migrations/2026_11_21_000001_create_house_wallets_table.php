<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Two house wallets (j_pool, admin_profit). Seeded at deploy time.
 * All money movements are recorded in house_wallet_transactions.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('house_wallets', function (Blueprint $table) {
            $table->id();
            $table->string('type')->unique();           // 'j_pool' | 'admin_profit'
            $table->decimal('balance', 18, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('house_wallets');
    }
};
