<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Peer-to-peer wallet transfers (user -> user by phone/email).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('recipient_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('amount', 18, 2);
            $table->string('status')->default('completed'); // completed, reversed
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index('sender_id');
            $table->index('recipient_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transfers');
    }
};
