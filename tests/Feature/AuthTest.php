<?php

namespace Tests\Feature;

use App\Services\OtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_api_returns_401_not_500(): void
    {
        // Plain GET (no Accept: application/json header) must still be 401 JSON.
        $this->get('/api/round/current')
            ->assertStatus(401)
            ->assertJson(['success' => false]);
    }

    public function test_send_otp_for_registration(): void
    {
        $res = $this->postJson('/api/send-otp', [
            'mobile' => '9876543210',
            'module' => 'register',
        ]);

        $res->assertOk()->assertJson(['success' => true]);
        $this->assertDatabaseHas('otps', ['mobile' => '9876543210', 'module' => 'register']);
        // Non-production returns the OTP for dev convenience.
        $this->assertIsInt($res->json('data'));
        $this->assertGreaterThan(0, $res->json('data'));
    }

    public function test_register_creates_user_wallet_gamer_then_login(): void
    {
        $mobile = '9876543210';
        $otp = app(OtpService::class)->generate($mobile, 'register');

        $register = $this->postJson('/api/register', [
            'mobile' => $mobile,
            'email' => 'p1@example.com',
            'password' => 'Bingo@123',
            'confirm_password' => 'Bingo@123',
            'otp' => $otp,
        ]);

        $register->assertOk()->assertJson(['success' => true]);
        $this->assertNotEmpty($register->json('data.token'));

        $this->assertDatabaseHas('users', ['mobile' => $mobile]);
        $this->assertDatabaseHas('wallets', ['user_id' => 1]);
        $this->assertDatabaseHas('gamers', ['user_id' => 1, 'kyc_status' => 'pending']);

        $login = $this->postJson('/api/login', [
            'credential' => $mobile,
            'password' => 'Bingo@123',
        ]);

        $login->assertOk()->assertJson(['success' => true]);
        $this->assertNotEmpty($login->json('data.token'));
    }

    public function test_validation_error_returns_specific_message(): void
    {
        $res = $this->postJson('/api/register', [
            'mobile' => '123',            // invalid format
            'password' => 'Bingo@123',
            'confirm_password' => 'Bingo@123',
            'otp' => '111111',
        ]);

        $res->assertStatus(422)
            ->assertJson(['success' => false])
            ->assertJsonPath('message', 'Enter a valid 10-digit mobile number.');
        $this->assertNotEmpty($res->json('data.errors'));
    }

    public function test_register_rejects_invalid_otp(): void
    {
        $mobile = '9876543210';
        app(OtpService::class)->generate($mobile, 'register');

        $res = $this->postJson('/api/register', [
            'mobile' => $mobile,
            'email' => 'p1@example.com',
            'password' => 'Bingo@123',
            'confirm_password' => 'Bingo@123',
            'otp' => '000000',
        ]);

        $res->assertStatus(422)->assertJson(['success' => false]);
    }

    public function test_forgot_password_then_reset(): void
    {
        $mobile = '9876543210';
        $otp = app(OtpService::class)->generate($mobile, 'register');
        $this->postJson('/api/register', [
            'mobile' => $mobile,
            'email' => 'p1@example.com',
            'password' => 'Bingo@123',
            'confirm_password' => 'Bingo@123',
            'otp' => $otp,
        ])->assertOk();

        $fotp = app(OtpService::class)->generate($mobile, 'forgot_password');
        $forgot = $this->postJson('/api/forgot-password', [
            'mobile' => $mobile,
            'otp' => $fotp,
        ]);
        $forgot->assertOk();
        $token = $forgot->json('data.token');
        $this->assertNotEmpty($token);

        $reset = $this->withToken($token)->postJson('/api/reset-password', [
            'password' => 'NewPass@123',
            'confirm_password' => 'NewPass@123',
        ]);
        $reset->assertOk()->assertJson(['success' => true]);

        $this->postJson('/api/login', [
            'credential' => $mobile,
            'password' => 'NewPass@123',
        ])->assertOk();
    }
}
