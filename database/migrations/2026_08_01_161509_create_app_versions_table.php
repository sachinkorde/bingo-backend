<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Released Android builds. The app checks this on launch to decide whether a
 * newer build exists and whether the player may keep playing on their current
 * one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_versions', function (Blueprint $table) {
            $table->id();

            // Android's versionCode — an integer that only ever increases.
            // Version comparison uses THIS; version_name is display only.
            $table->unsignedInteger('version_code')->unique();
            $table->string('version_name');                  // e.g. "1.0.4"

            // The APK itself: either an uploaded file OR an external URL.
            // External is safer on hosts with an ephemeral filesystem, where
            // uploaded files are wiped on every redeploy.
            $table->string('apk_file')->nullable();
            $table->string('download_url')->nullable();

            $table->text('changelog')->nullable();

            // true  = players on older builds are BLOCKED until they update
            // false = they are told an update exists but can dismiss it
            $table->boolean('is_mandatory')->default(false);

            // Only the active release with the highest version_code is served.
            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_versions');
    }
};
