<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Withdrawal;
use App\Services\OtpService;
use App\Services\WithdrawalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WithdrawalTest extends TestCase
{
    use RefreshDatabase;

    private function setupVerifiedUser(string $mobile): string
    {
        $otp = app(OtpService::class)->generate($mobile, 'register');
        $token = $this->postJson('/api/register', [
            'mobile' => $mobile,
            'email' => $mobile . '@example.com',
            'password' => 'Bingo@123',
            'confirm_password' => 'Bingo@123',
            'otp' => $otp,
        ])->json('data.token');

        $this->withToken($token)->postJson('/api/add-amount', ['amount' => 1000])->assertOk();

        $user = User::where('mobile', $mobile)->first();
        $user->gamer->update(['kyc_status' => 'verified']);

        $this->withToken($token)->postJson('/api/bank-detail', [
            'account_holder_name' => 'Test User',
            'account_number' => '1234567890',
            'ifsc_code' => 'HDFC0000123',
            'upi_id' => $mobile . '@ybl',
        ])->assertOk();

        return $token;
    }

    public function test_reject_refunds_held_funds(): void
    {
        $token = $this->setupVerifiedUser('9876520000');

        $w = $this->withToken($token)->postJson('/api/withdraw', ['amount' => 400]);
        $w->assertOk();
        $this->assertEquals('600.00', $w->json('data.balance')); // held

        $withdrawal = Withdrawal::first();
        app(WithdrawalService::class)->reject($withdrawal, null, 'test reject');

        $this->assertEquals('1000.00', $withdrawal->user->wallet->fresh()->balance);
        $this->assertDatabaseHas('withdrawals', ['id' => $withdrawal->id, 'status' => 'rejected']);
    }

    public function test_approve_keeps_funds_held(): void
    {
        $token = $this->setupVerifiedUser('9876520001');

        $this->withToken($token)->postJson('/api/withdraw', ['amount' => 400])->assertOk();

        $withdrawal = Withdrawal::first();
        app(WithdrawalService::class)->approve($withdrawal, null, 'TXN123');

        $this->assertEquals('600.00', $withdrawal->user->wallet->fresh()->balance);
        $this->assertDatabaseHas('withdrawals', ['id' => $withdrawal->id, 'status' => 'paid']);
    }
}
