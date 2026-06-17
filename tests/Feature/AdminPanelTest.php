<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_users_list_and_user_page(): void
    {
        $admin = User::create([
            'name' => 'Admin',
            'mobile' => '9999999999',
            'email' => 'admin@realbingo.test',
            'password' => 'Admin@123',
            'role' => 'admin',
            'status' => 'active',
        ]);

        $player = User::create([
            'mobile' => '9000000010',
            'password' => 'secret123',
            'role' => 'user',
            'status' => 'active',
        ]);

        $this->actingAs($admin);

        // Users list (with the new Balance / Win % / Net columns).
        $this->get('/admin/users')->assertOk();

        // The per-user page (with Bets / Deposits / Withdrawals / Transactions tabs).
        $this->get("/admin/users/{$player->id}/edit")->assertOk();
    }
}
