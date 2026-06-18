<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database with a clean baseline.
     */
    public function run(): void
    {
        $this->call([
            AdminUserSeeder::class,    // superadmin (owner) login
            SettingsSeeder::class,     // referral bonus, etc.
            DemoPlayersSeeder::class,  // a few demo players so the dashboard isn't empty
        ]);
    }
}
