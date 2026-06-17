<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Money-out requests. Require KYC-verified status and admin approval before
 * payout via the provider. Funds are held (debited) at request time.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('withdrawals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 18, 2);
            $table->string('method')->default('bank');       // bank, upi, crypto
            $table->foreignId('bank_detail_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('pending');    // pending, approved, rejected, paid
            $table->foreignId('processed_by')->nullable();   // admin user id
            $table->string('provider_ref')->nullable();
            $table->text('remark')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('withdrawals');
    }
};
