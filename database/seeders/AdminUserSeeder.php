<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['mobile' => '9999999999'],
            [
                'name' => 'Admin',
                'email' => 'admin@realbingo.test',
                'password' => 'Admin@123', // hashed by the model cast
                'role' => 'admin',
                'status' => 'active',
                'referral_code' => 'ADMIN001',
            ]
        );
    }
}
