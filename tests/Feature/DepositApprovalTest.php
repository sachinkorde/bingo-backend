<?php

namespace Tests\Feature;

use App\Models\Deposit;
use App\Models\User;
use App\Services\DepositService;
use App\Services\OtpService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DepositApprovalTest extends TestCase
{
    use RefreshDatabase;

    private function registerUser(string $mobile): User
    {
        $otp = app(OtpService::class)->generate($mobile, 'register');

        $this->postJson('/api/register', [
            'mobile' => $mobile,
            'password' => 'Bingo@123',
            'confirm_password' => 'Bingo@123',
            'otp' => $otp,
        ])->assertOk();

        return User::where('mobile', $mobile)->firstOrFail();
    }

    public function test_pending_deposit_does_not_credit_the_wallet(): void
    {
        $user = $this->registerUser('9876530001');

        $deposit = Deposit::create([
            'user_id' => $user->id,
            'amount' => '10000.00',
            'source' => 'bank',
            'status' => 'pending',
        ]);

        $this->assertSame('0.00', app(WalletService::class)->balance($user));
        $this->assertSame('pending', $deposit->status);
    }

    public function test_approving_a_deposit_credits_the_wallet(): void
    {
        $user = $this->registerUser('9876530002');

        $deposit = Deposit::create([
            'user_id' => $user->id,
            'amount' => '10000.00',
            'source' => 'bank',
            'status' => 'pending',
        ]);

        $this->assertTrue(app(DepositService::class)->approve($deposit));

        $this->assertSame('10000.00', app(WalletService::class)->balance($user->fresh()));
        $this->assertSame('success', $deposit->fresh()->status);
    }

    public function test_approving_twice_credits_only_once(): void
    {
        $user = $this->registerUser('9876530003');

        $deposit = Deposit::create([
            'user_id' => $user->id,
            'amount' => '10000.00',
            'source' => 'bank',
            'status' => 'pending',
        ]);

        $deposits = app(DepositService::class);

        $this->assertTrue($deposits->approve($deposit));
        $this->assertFalse($deposits->approve($deposit->fresh()));

        // Double-crediting here would be free money for the player.
        $this->assertSame('10000.00', app(WalletService::class)->balance($user->fresh()));
    }

    public function test_rejecting_a_deposit_credits_nothing(): void
    {
        $user = $this->registerUser('9876530004');

        $deposit = Deposit::create([
            'user_id' => $user->id,
            'amount' => '10000.00',
            'source' => 'bank',
            'status' => 'pending',
        ]);

        $this->assertTrue(app(DepositService::class)->reject($deposit, 'Payment never arrived'));

        $this->assertSame('0.00', app(WalletService::class)->balance($user->fresh()));
        $this->assertSame('failed', $deposit->fresh()->status);
    }
}
