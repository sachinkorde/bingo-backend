<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admins are completely separate from players (users). The dashboard logs in
 * against THIS table via the 'admin' guard. Roles: superadmin, admin, subadmin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admins', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('role')->default('subadmin');     // superadmin, admin, subadmin
            $table->foreignId('created_by')->nullable();      // the admin who created this one
            $table->json('permissions')->nullable();          // resource keys a subadmin may view
            $table->string('status')->default('active');      // active, disabled
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admins');
    }
};
