<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\OtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReferralTest extends TestCase
{
    use RefreshDatabase;

    private function register(string $mobile, ?string $referral = null): User
    {
        $otp = app(OtpService::class)->generate($mobile, 'register');

        $payload = [
            'mobile' => $mobile,
            'email' => $mobile . '@example.com',
            'password' => 'Bingo@123',
            'confirm_password' => 'Bingo@123',
            'otp' => $otp,
        ];
        if ($referral) {
            $payload['referral'] = $referral;
        }

        $this->postJson('/api/register', $payload)->assertOk();

        return User::where('mobile', $mobile)->firstOrFail();
    }

    public function test_inviter_is_paid_on_invitee_first_deposit(): void
    {
        config(['game.referral_bonus' => 50]);

        $inviter = $this->register('9876510000');
        $invitee = $this->register('9876510001', $inviter->referral_code);

        $this->assertEquals($inviter->referral_code, $invitee->referred_by);

        // Log in as invitee and make first deposit.
        $token = $this->postJson('/api/login', [
            'credential' => '9876510001',
            'password' => 'Bingo@123',
        ])->json('data.token');

        $this->withToken($token)->postJson('/api/add-amount', ['amount' => 500])->assertOk();

        // Inviter should have received the 50 bonus.
        $this->assertEquals('50.00', $inviter->wallet()->first()->balance);
        $this->assertDatabaseHas('wallet_transactions', [
            'user_id' => $inviter->id,
            'type' => 'referral_bonus',
            'reference' => "referral:{$invitee->id}",
        ]);

        // A second deposit must NOT pay the inviter again.
        $this->withToken($token)->postJson('/api/add-amount', ['amount' => 200])->assertOk();
        $this->assertEquals('50.00', $inviter->wallet()->first()->balance);
    }
}
