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

    public function test_register_and_login_with_username(): void
    {
        $mobile = '9876543211';
        $otp = app(OtpService::class)->generate($mobile, 'register');

        $register = $this->postJson('/api/register', [
            'username' => 'player_one',
            'mobile' => $mobile,
            'email' => 'playerone@example.com',
            'password' => 'Bingo@123',
            'confirm_password' => 'Bingo@123',
            'otp' => $otp,
        ]);

        $register->assertOk()->assertJson(['success' => true]);
        $this->assertDatabaseHas('users', ['mobile' => $mobile, 'username' => 'player_one']);

        // Login using username credential
        $login = $this->postJson('/api/login', [
            'credential' => 'player_one',
            'password' => 'Bingo@123',
        ]);

        $login->assertOk()->assertJson(['success' => true]);
        $this->assertNotEmpty($login->json('data.token'));
    }

    public function test_register_rejects_duplicate_username(): void
    {
        $mobile1 = '9876543211';
        $otp1 = app(OtpService::class)->generate($mobile1, 'register');
        $this->postJson('/api/register', [
            'username' => 'unique_gamer',
            'mobile' => $mobile1,
            'password' => 'Bingo@123',
            'confirm_password' => 'Bingo@123',
            'otp' => $otp1,
        ])->assertOk();

        $mobile2 = '9876543212';
        $otp2 = app(OtpService::class)->generate($mobile2, 'register');
        $res = $this->postJson('/api/register', [
            'username' => 'unique_gamer',
            'mobile' => $mobile2,
            'password' => 'Bingo@123',
            'confirm_password' => 'Bingo@123',
            'otp' => $otp2,
        ]);

        $res->assertStatus(422)
            ->assertJson(['success' => false])
            ->assertJsonPath('message', 'This username is already taken.');
    }

    public function test_check_username_endpoint(): void
    {
        $this->getJson('/api/check-username?username=available_user')
            ->assertOk()
            ->assertJson(['success' => true, 'message' => 'Username available!']);

        $mobile = '9876543211';
        $otp = app(OtpService::class)->generate($mobile, 'register');
        $this->postJson('/api/register', [
            'username' => 'available_user',
            'mobile' => $mobile,
            'password' => 'Bingo@123',
            'confirm_password' => 'Bingo@123',
            'otp' => $otp,
        ])->assertOk();

        $this->getJson('/api/check-username?username=available_user')
            ->assertStatus(422)
            ->assertJson(['success' => false, 'message' => 'Username already exists.']);
    }

    public function test_otp_length_configurable_4_or_6_digits(): void
    {
        // Default (6 digits)
        \App\Models\Setting::put('otp_digits', '6');
        $otp6 = app(OtpService::class)->generate('9876543299', 'register');
        $this->assertEquals(6, strlen($otp6));

        // 4 digits toggle
        \App\Models\Setting::put('otp_digits', '4');
        $otp4 = app(OtpService::class)->generate('9876543298', 'register');
        $this->assertEquals(4, strlen($otp4));

        // Verification with 4 digit OTP
        $register = $this->postJson('/api/register', [
            'mobile' => '9876543298',
            'password' => 'Bingo@123',
            'confirm_password' => 'Bingo@123',
            'otp' => $otp4,
        ]);
        $register->assertOk()->assertJson(['success' => true]);
    }
}
