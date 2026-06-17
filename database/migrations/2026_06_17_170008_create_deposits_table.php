<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Money-in records. Credited to the wallet only after the payment provider
 * confirms (status = success). The provider plugs in via a provider adapter.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deposits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 18, 2);
            $table->string('source')->default('bank');       // bank, upi, crypto
            $table->string('provider')->nullable();
            $table->string('provider_ref')->nullable();
            $table->string('status')->default('pending');    // pending, success, failed
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deposits');
    }
};
