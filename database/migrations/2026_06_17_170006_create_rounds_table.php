<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A betting round ("slot"). The server opens it, runs the betting window,
 * picks the winning number with fair RNG, and settles payouts.
 * server_seed/server_seed_hash support provably-fair verification.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rounds', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('slot_no')->unique();
            $table->string('status')->default('betting');    // betting, closed, settled
            $table->timestamp('betting_started_at')->nullable();
            $table->timestamp('betting_closes_at')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->unsignedTinyInteger('winning_number')->nullable();
            $table->string('server_seed')->nullable();       // revealed after settlement
            $table->string('server_seed_hash')->nullable();  // published before betting closes
            $table->decimal('total_bet', 18, 2)->default(0);
            $table->decimal('total_payout', 18, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rounds');
    }
};
