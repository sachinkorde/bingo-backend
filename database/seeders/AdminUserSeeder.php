<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Seeder;

/**
 * Dev convenience: seeds a default SUPERADMIN into the admins table.
 * For real/production use, create the superadmin with:
 *   php artisan bingo:create-superadmin
 */
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        Admin::updateOrCreate(
            ['email' => 'superadmin@realbingo.test'],
            [
                'name' => 'Super Admin',
                'password' => 'Super@123', // hashed by the model cast
                'role' => 'superadmin',
                'status' => 'active',
            ]
        );
    }
}
