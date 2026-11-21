<?php

namespace Database\Seeders;

use App\Models\HouseWallet;
use Illuminate\Database\Seeder;

class HouseWalletSeeder extends Seeder
{
    public function run(): void
    {
        HouseWallet::firstOrCreate(['type' => 'j_pool'],       ['balance' => '0.00']);
        HouseWallet::firstOrCreate(['type' => 'admin_profit'], ['balance' => '0.00']);
    }
}
