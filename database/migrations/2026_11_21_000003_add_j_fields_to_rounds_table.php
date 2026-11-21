<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Track per-round J-win status and profit distribution for admin visibility.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rounds', function (Blueprint $table) {
            $table->boolean('winning_is_j')->default(false)->after('winning_number');
            $table->decimal('j_pool_contribution', 18, 2)->default(0)->after('total_payout');
            $table->decimal('admin_profit_amount', 18, 2)->default(0)->after('j_pool_contribution');
        });
    }

    public function down(): void
    {
        Schema::table('rounds', function (Blueprint $table) {
            $table->dropColumn(['winning_is_j', 'j_pool_contribution', 'admin_profit_amount']);
        });
    }
};
