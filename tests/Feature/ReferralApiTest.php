<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\OtpService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReferralApiTest extends TestCase
{
    use RefreshDatabase;

    private function register(string $mobile, ?string $referral = null): array
    {
        $otp = app(OtpService::class)->generate($mobile, 'register');

        $token = $this->postJson('/api/register', [
            'mobile' => $mobile,
            'password' => 'Bingo@123',
            'confirm_password' => 'Bingo@123',
            'otp' => $otp,
            'referral' => $referral,
        ])->json('data.token');

        return [$token, User::where('mobile', $mobile)->firstOrFail()];
    }

    public function test_summary_reflects_referred_count_and_earnings(): void
    {
        [$inviterToken, $inviter] = $this->register('9876550000');

        [, $invitee] = $this->register('9876550001', $inviter->referral_code);

        // First successful deposit triggers the referral bonus.
        app(WalletService::class)->credit($invitee, 100, 'deposit', 'test');
        \App\Models\Deposit::create([
            'user_id' => $invitee->id,
            'amount' => '100.00',
            'source' => 'bank',
            'status' => 'success',
        ]);
        app(\App\Services\ReferralService::class)->rewardInviterOnFirstDeposit($invitee->fresh());

        $res = $this->withToken($inviterToken)->getJson('/api/referrals/summary')->assertOk();

        $this->assertSame($inviter->referral_code, $res->json('data.referral_code'));
        $this->assertSame(1, $res->json('data.total_referred'));
        $this->assertSame('50.00', $res->json('data.total_earned'));
    }

    public function test_list_shows_pending_then_earned_status(): void
    {
        [$inviterToken, $inviter] = $this->register('9876550002');
        [, $invitee] = $this->register('9876550003', $inviter->referral_code);

        $pending = $this->withToken($inviterToken)->getJson('/api/referrals')->assertOk();
        $this->assertSame('pending', $pending->json('data.referrals.0.status'));
        $this->assertSame('98••••0003', $pending->json('data.referrals.0.mobile_masked'));

        app(WalletService::class)->credit($invitee, 100, 'deposit', 'test');
        \App\Models\Deposit::create([
            'user_id' => $invitee->id,
            'amount' => '100.00',
            'source' => 'bank',
            'status' => 'success',
        ]);
        app(\App\Services\ReferralService::class)->rewardInviterOnFirstDeposit($invitee->fresh());

        $earned = $this->withToken($inviterToken)->getJson('/api/referrals')->assertOk();
        $this->assertSame('earned', $earned->json('data.referrals.0.status'));
    }

    public function test_referrals_relationship_resolves_via_referral_code(): void
    {
        [, $inviter] = $this->register('9876550004');
        [, $invitee] = $this->register('9876550005', $inviter->referral_code);

        $this->assertTrue($inviter->referrals()->whereKey($invitee->id)->exists());
    }

    public function test_summary_includes_share_link(): void
    {
        [$inviterToken, $inviter] = $this->register('9876550006');
        $res = $this->withToken($inviterToken)->getJson('/api/referrals/summary')->assertOk();

        $this->assertStringContainsString('/ref/' . $inviter->referral_code, $res->json('data.share_link'));
    }

    public function test_referral_link_web_redirect(): void
    {
        $res = $this->get('/ref/TRB12345');
        $res->assertRedirect(route('download.apk', ['ref' => 'TRB12345']));
    }

    public function test_agent_id_auto_referral_attribution_and_first_deposit_bonus(): void
    {
        [$inviterToken, $inviter] = $this->register('9876550007');

        // Register using agent_id captured from deep link
        $otp = app(OtpService::class)->generate('9876550008', 'register');
        $reg = $this->postJson('/api/register', [
            'mobile' => '9876550008',
            'password' => 'Bingo@123',
            'confirm_password' => 'Bingo@123',
            'otp' => $otp,
            'agent_id' => $inviter->referral_code,
        ])->assertOk();

        $invitee = User::where('mobile', '9876550008')->firstOrFail();
        $this->assertSame($inviter->referral_code, $invitee->referred_by);

        // Deposit approval triggers first deposit bonus to inviter
        $deposit = \App\Models\Deposit::create([
            'user_id' => $invitee->id,
            'amount' => '500.00',
            'source' => 'bank',
            'status' => 'pending',
        ]);

        app(\App\Services\DepositService::class)->approve($deposit);

        $this->assertEquals(50.0, (float) $inviter->referralEarnings());
    }
}
