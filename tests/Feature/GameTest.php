<?php

namespace Tests\Feature;

use App\Models\Round;
use App\Services\OtpService;
use App\Services\RoundService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class GameTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Pin to second 0 of a minute so betting is open during the test.
        Carbon::setTestNow('2026-06-18 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function registerAndFund(string $mobile, float $amount): string
    {
        $otp = app(OtpService::class)->generate($mobile, 'register');
        $token = $this->postJson('/api/register', [
            'mobile' => $mobile,
            'email' => $mobile . '@example.com',
            'password' => 'Bingo@123',
            'confirm_password' => 'Bingo@123',
            'otp' => $otp,
        ])->json('data.token');

        if ($amount > 0) {
            $this->withToken($token)->postJson('/api/add-amount', [
                'amount' => $amount,
                'source' => 'bank',
            ])->assertOk();
        }

        return $token;
    }

    public function test_place_bid_on_fair_winner_pays_out(): void
    {
        $token = $this->registerAndFund('9876500000', 1000);

        $session = $this->withToken($token)->getJson('/api/round/current')->json('data');
        $slotId = $session['slot_id'];
        $slotNo = $session['slot_no'];

        // Compute the fair winner for this round (derived from the seed).
        $round = Round::find($slotId);
        $winner = app(RoundService::class)->deriveWinningNumber($round);

        $bid = $this->withToken($token)->postJson('/api/place-bid', [
            'slot_no' => $slotNo,
            'bids' => json_encode([[(string) $winner => 100]]),
        ]);

        $bid->assertOk()->assertJson(['success' => true]);
        $this->assertEquals('900.00', $bid->json('data.balance')); // 1000 - 100

        // Settle the round (server picks winner from committed seed).
        app(RoundService::class)->settle($round);

        $result = $this->withToken($token)->getJson("/api/round/{$slotId}/result");
        $result->assertOk();
        $this->assertEquals($winner, $result->json('data.winning_number'));

        // 100 bet * 9 payout = 900; balance 900 + 900 = 1800.
        $balance = $this->withToken($token)->getJson('/api/wallet/balance')->json('data.balance');
        $this->assertEquals('1800.00', $balance);
    }

    public function test_payout_credited_when_betting_closes_same_session(): void
    {
        $token = $this->registerAndFund('9876500009', 1000);

        $session = $this->withToken($token)->getJson('/api/round/current')->json('data');
        $slotId = $session['slot_id'];
        $slotNo = $session['slot_no'];

        $round = Round::find($slotId);
        $winner = app(RoundService::class)->deriveWinningNumber($round);

        $this->withToken($token)->postJson('/api/place-bid', [
            'slot_no' => $slotNo,
            'bids' => json_encode([[(string) $winner => 100]]),
        ])->assertOk();

        // Jump to the result phase of the SAME minute/slot (second 55).
        Carbon::setTestNow('2026-06-18 10:00:55');

        // Fetching the result now settles it and pays the winner immediately.
        $result = $this->withToken($token)->getJson("/api/round/{$slotId}/result");
        $result->assertOk();
        $this->assertEquals($winner, $result->json('data.winning_number'));
        $this->assertEquals('900.00', $result->json('data.your_payout'));

        $balance = $this->withToken($token)->getJson('/api/wallet/balance')->json('data.balance');
        $this->assertEquals('1800.00', $balance); // 900 left after bet + 900 payout
    }

    public function test_bet_rejected_without_funds(): void
    {
        $token = $this->registerAndFund('9876500001', 0);

        $slotNo = $this->withToken($token)->getJson('/api/round/current')->json('data.slot_no');

        $res = $this->withToken($token)->postJson('/api/place-bid', [
            'slot_no' => $slotNo,
            'bids' => json_encode([['1' => 100]]),
        ]);

        $res->assertStatus(422)->assertJson(['success' => false]);
    }

    public function test_bet_rejects_invalid_number(): void
    {
        $token = $this->registerAndFund('9876500002', 1000);
        $slotNo = $this->withToken($token)->getJson('/api/round/current')->json('data.slot_no');

        $res = $this->withToken($token)->postJson('/api/place-bid', [
            'slot_no' => $slotNo,
            'bids' => json_encode([['99' => 100]]),
        ]);

        $res->assertStatus(422);
    }

    public function test_withdraw_blocked_without_kyc(): void
    {
        $token = $this->registerAndFund('9876500003', 1000);

        $res = $this->withToken($token)->postJson('/api/withdraw', ['amount' => 100]);
        $res->assertStatus(403)->assertJson(['success' => false]);
    }
}
