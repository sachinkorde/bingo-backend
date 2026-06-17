<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    private function player(): User
    {
        return User::create([
            'mobile' => '9000000010',
            'password' => 'secret123',
            'role' => 'user',
            'status' => 'active',
        ]);
    }

    public function test_superadmin_can_access_everything(): void
    {
        $super = Admin::create([
            'name' => 'Super',
            'email' => 'super@x.com',
            'password' => 'secret123',
            'role' => 'superadmin',
            'status' => 'active',
        ]);
        $player = $this->player();

        $this->actingAs($super, 'admin');

        $this->get('/admin/users')->assertOk();
        $this->get("/admin/users/{$player->id}/edit")->assertOk();
        $this->get('/admin/admins')->assertOk();        // can manage operators
        $this->get('/admin/withdrawals')->assertOk();
    }

    public function test_subadmin_only_sees_permitted_resources(): void
    {
        $sub = Admin::create([
            'name' => 'Sub',
            'email' => 'sub@x.com',
            'password' => 'secret123',
            'role' => 'subadmin',
            'status' => 'active',
            'permissions' => ['deposits'], // may ONLY see deposits
        ]);

        $this->actingAs($sub, 'admin');

        $this->get('/admin/deposits')->assertOk();       // permitted
        $this->get('/admin/users')->assertForbidden();   // not permitted
        $this->get('/admin/admins')->assertForbidden();  // subadmins can't manage operators
    }

    public function test_player_cannot_log_into_dashboard_guard(): void
    {
        // A player authenticated on the web guard is NOT an admin-guard user.
        $this->actingAs($this->player(), 'web');
        $this->get('/admin/users')->assertRedirect(); // bounced to admin login
    }
}
