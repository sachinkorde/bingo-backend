<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per (user, number) bet within a round. Settled at round end.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('round_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('number');           // 0-9
            $table->decimal('amount', 18, 2);
            $table->decimal('payout', 18, 2)->default(0);
            $table->boolean('is_winner')->default(false);
            $table->timestamps();

            $table->index(['round_id', 'number']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bets');
    }
};
