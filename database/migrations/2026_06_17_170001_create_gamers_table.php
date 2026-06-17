<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Player profile + KYC data (1:1 with users).
 * Mirrors the "gamer" object the Unity client expects in the profile response.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gamers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->string('gender')->nullable();           // male, female, other
            $table->string('date_of_birth')->nullable();    // stored as given (e.g. 1999/10/20)
            $table->text('address')->nullable();
            $table->string('adhar_number')->nullable();
            $table->string('pan_number')->nullable();
            $table->string('adhar_document')->nullable();   // stored file path
            $table->string('pan_document')->nullable();
            $table->string('profile_image')->nullable();
            $table->string('kyc_status')->default('pending'); // pending, verified, rejected
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gamers');
    }
};
