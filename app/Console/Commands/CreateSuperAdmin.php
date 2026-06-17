<?php

namespace App\Console\Commands;

use App\Models\Admin;
use Illuminate\Console\Command;

/**
 * Creates (or updates) the SUPERADMIN — the owner account. This is the only
 * way a superadmin is made: a deliberate, separate command, isolated from
 * players and from the normal seeders.
 *
 *   php artisan bingo:create-superadmin
 *   php artisan bingo:create-superadmin --name="Owner" --email=owner@x.com --password=Secret123
 */
class CreateSuperAdmin extends Command
{
    protected $signature = 'bingo:create-superadmin {--name=} {--email=} {--password=}';

    protected $description = 'Create or update the superadmin (owner) operator account.';

    public function handle(): int
    {
        $name = $this->option('name') ?: $this->ask('Superadmin name');
        $email = $this->option('email') ?: $this->ask('Superadmin email');
        $password = $this->option('password') ?: $this->secret('Superadmin password');

        if (empty($name) || empty($email) || empty($password)) {
            $this->error('Name, email and password are all required.');

            return self::FAILURE;
        }

        $admin = Admin::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => $password, // hashed by the model cast
                'role' => 'superadmin',
                'status' => 'active',
            ]
        );

        $this->info("Superadmin ready: {$admin->email}");

        return self::SUCCESS;
    }
}
