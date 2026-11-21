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
            GameSettingSeeder::class,  // J-pool %, hourly limit, delay settings
            HouseWalletSeeder::class,  // j_pool + admin_profit house wallets
            DemoPlayersSeeder::class,  // a few demo players so the dashboard isn't empty
        ]);
    }
}
