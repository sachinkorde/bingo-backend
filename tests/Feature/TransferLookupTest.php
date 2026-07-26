<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\OtpService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransferLookupTest extends TestCase
{
    use RefreshDatabase;

    private function register(string $mobile, float $fund = 0): string
    {
        $otp = app(OtpService::class)->generate($mobile, 'register');

        $token = $this->postJson('/api/register', [
            'mobile' => $mobile,
            'email' => $mobile . '@example.com',
            'password' => 'Bingo@123',
            'confirm_password' => 'Bingo@123',
            'otp' => $otp,
        ])->json('data.token');

        if ($fund > 0) {
            $user = User::where('mobile', $mobile)->firstOrFail();
            app(WalletService::class)->credit($user, $fund, 'deposit', 'test');
        }

        return $token;
    }

    public function test_transfer_by_username_works(): void
    {
        $senderToken = $this->register('9876540000', 1000);
        $this->register('9876540001');

        $recipient = User::where('mobile', '9876540001')->firstOrFail();

        $this->withToken($senderToken)->postJson('/api/transfer', [
            'recipient' => $recipient->referral_code,   // username, not mobile
            'amount' => 250,
        ])->assertOk();

        $this->assertSame('250.00', app(WalletService::class)->balance($recipient->fresh()));
    }

    public function test_username_lookup_is_case_insensitive(): void
    {
        $senderToken = $this->register('9876540002', 500);
        $this->register('9876540003');

        $recipient = User::where('mobile', '9876540003')->firstOrFail();

        $this->withToken($senderToken)->postJson('/api/transfer', [
            'recipient' => strtolower($recipient->referral_code),
            'amount' => 100,
        ])->assertOk();

        $this->assertSame('100.00', app(WalletService::class)->balance($recipient->fresh()));
    }

    public function test_lookup_returns_masked_recipient_without_moving_money(): void
    {
        $senderToken = $this->register('9876540004', 1000);
        $this->register('9876540005');

        $res = $this->withToken($senderToken)
            ->getJson('/api/transfer/lookup?recipient=9876540005')
            ->assertOk();

        // Enough to recognise a friend, not enough to harvest a stranger.
        $this->assertSame('98••••0005', $res->json('data.mobile_masked'));

        // Nothing moved.
        $sender = User::where('mobile', '9876540004')->firstOrFail();
        $this->assertSame('1000.00', app(WalletService::class)->balance($sender));
    }

    public function test_lookup_rejects_unknown_recipient(): void
    {
        $senderToken = $this->register('9876540006', 100);

        $this->withToken($senderToken)
            ->getJson('/api/transfer/lookup?recipient=9999999999')
            ->assertStatus(404);
    }

    public function test_transfer_below_minimum_is_rejected(): void
    {
        $senderToken = $this->register('9876540007', 1000);
        $this->register('9876540008');

        $this->withToken($senderToken)->postJson('/api/transfer', [
            'recipient' => '9876540008',
            'amount' => 1,   // below the configured minimum
        ])->assertStatus(422);
    }

    public function test_daily_transfer_cap_is_enforced(): void
    {
        config(['game.transfer_max_per_day' => 500]);

        $senderToken = $this->register('9876540009', 5000);
        $this->register('9876540010');

        $this->withToken($senderToken)->postJson('/api/transfer', [
            'recipient' => '9876540010',
            'amount' => 400,
        ])->assertOk();

        // 400 + 200 would exceed the 500/day cap.
        $this->withToken($senderToken)->postJson('/api/transfer', [
            'recipient' => '9876540010',
            'amount' => 200,
        ])->assertStatus(422);

        $recipient = User::where('mobile', '9876540010')->firstOrFail();
        $this->assertSame('400.00', app(WalletService::class)->balance($recipient->fresh()));
    }
}
