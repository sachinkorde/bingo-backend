<?php

namespace Tests\Feature;

use App\Models\HouseWallet;
use App\Models\Round;
use App\Models\Setting;
use App\Services\HouseWalletService;
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

        // Ensure house wallets exist for every test.
        HouseWallet::firstOrCreate(['type' => 'j_pool'],       ['balance' => '0.00']);
        HouseWallet::firstOrCreate(['type' => 'admin_profit'], ['balance' => '0.00']);

        // Seed J settings with known defaults.
        Setting::put('j_enabled',           '1');
        Setting::put('j_test_mode',         '0');
        Setting::put('j_force_next_round',  '0');
        Setting::put('max_bet_per_number',  '1000000');
        Setting::put('transfer_max_per_day','500000');
        Setting::put('j_pool_pct',          '10');
        Setting::put('j_max_per_hour',       '3');
        Setting::put('j_delay_min_rounds',   '2');
        Setting::put('j_delay_max_rounds',   '5');
        Setting::put('j_pending_until_slot', '0');
        Setting::put('j_hour_key',           '');
        Setting::put('j_hour_count',         '0');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function registerAndFund(string $mobile, float $amount): string
    {
        $otp   = app(OtpService::class)->generate($mobile, 'register');
        $token = $this->postJson('/api/register', [
            'mobile'           => $mobile,
            'email'            => $mobile . '@example.com',
            'password'         => 'Bingo@123',
            'confirm_password' => 'Bingo@123',
            'otp'              => $otp,
        ])->json('data.token');

        if ($amount > 0) {
            $this->withToken($token)->postJson('/api/add-amount', [
                'amount' => $amount,
                'source' => 'bank',
            ])->assertOk();
        }

        return $token;
    }

    private function seedJPool(float $amount): void
    {
        app(HouseWalletService::class)->credit('j_pool', (string) $amount, 'manual', 'test-seed');
    }

    // ── Existing tests (updated for "j" serialization) ───────────────────────

    public function test_place_bid_on_house_safe_winner_pays_out(): void
    {
        $token = $this->registerAndFund('9876500000', 1000);

        $session = $this->withToken($token)->getJson('/api/round/current')->json('data');
        $slotId  = $session['slot_id'];
        $slotNo  = $session['slot_no'];

        // Bet ₹100 on number 0 only.
        // Numbers 1-9 all have ₹0 → one of numbers 1-9 will win (lowest bet liability).
        $bid = $this->withToken($token)->postJson('/api/place-bid', [
            'slot_no' => $slotNo,
            'bids'    => json_encode([['0' => 100]]),
        ]);

        $bid->assertOk()->assertJson(['success' => true]);
        $this->assertEquals('900.00', $bid->json('data.balance'));

        $round = Round::find($slotId);
        app(RoundService::class)->settle($round);

        $result = $this->withToken($token)->getJson("/api/round/{$slotId}/result");
        $result->assertOk();
        // Number 0 had a bet; numbers 1-9 had zero bets, so the winner must be from 1-9.
        $winningNum = (int) $result->json('data.winning_number');
        $this->assertContains($winningNum, range(1, 9));
        $this->assertNotEquals(0, $winningNum);
        $balance = $this->withToken($token)->getJson('/api/wallet/balance')->json('data.balance');
        $this->assertEquals('900.00', $balance);
    }

    public function test_house_safe_winner_pays_out_when_all_numbers_bet_equally(): void
    {
        $token = $this->registerAndFund('9876500000', 10000);

        $session = $this->withToken($token)->getJson('/api/round/current')->json('data');
        $slotId  = $session['slot_id'];
        $slotNo  = $session['slot_no'];

        // Bet ₹100 on every number 0-9. All totals equal → a random candidate 0-9 wins.
        $bids = [];
        for ($i = 0; $i < 10; $i++) {
            $bids[(string) $i] = 100;
        }
        $this->withToken($token)->postJson('/api/place-bid', [
            'slot_no' => $slotNo,
            'bids'    => json_encode([$bids]),
        ])->assertOk();

        $round = Round::find($slotId);
        app(RoundService::class)->settle($round);

        $result = $this->withToken($token)->getJson("/api/round/{$slotId}/result");
        $result->assertOk();
        $this->assertContains((int) $result->json('data.winning_number'), range(0, 9));
        // ₹100 on winning number → 9× → ₹900 payout. Spent ₹1000. Balance = 10000 - 1000 + 900 = 9900.
        $balance = $this->withToken($token)->getJson('/api/wallet/balance')->json('data.balance');
        $this->assertEquals('9900.00', $balance);
    }

    public function test_payout_credited_when_betting_closes_same_session(): void
    {
        $token = $this->registerAndFund('9876500009', 1000);

        $session = $this->withToken($token)->getJson('/api/round/current')->json('data');
        $slotId  = $session['slot_id'];
        $slotNo  = $session['slot_no'];

        $bids = [];
        for ($i = 0; $i < 10; $i++) {
            $bids[(string) $i] = 10;
        }
        $this->withToken($token)->postJson('/api/place-bid', [
            'slot_no' => $slotNo,
            'bids'    => json_encode([$bids]),
        ])->assertOk();

        Carbon::setTestNow('2026-06-18 10:00:55');

        $result = $this->withToken($token)->getJson("/api/round/{$slotId}/result");
        $result->assertOk();
        $this->assertContains((int) $result->json('data.winning_number'), range(0, 9));
        $this->assertEquals('90.00', $result->json('data.your_payout'));

        $balance = $this->withToken($token)->getJson('/api/wallet/balance')->json('data.balance');
        $this->assertEquals('990.00', $balance);
    }

    public function test_empty_round_without_bets_picks_random_number(): void
    {
        $rounds = app(RoundService::class);
        $session = $rounds->currentSession();

        $round = $rounds->settle($session);
        $this->assertNotNull($round->winning_number);
        $this->assertContains((int) $round->winning_number, range(0, 9));
    }

    public function test_bet_rejected_without_funds(): void
    {
        $token  = $this->registerAndFund('9876500001', 0);
        $slotNo = $this->withToken($token)->getJson('/api/round/current')->json('data.slot_no');

        $res = $this->withToken($token)->postJson('/api/place-bid', [
            'slot_no' => $slotNo,
            'bids'    => json_encode([['1' => 100]]),
        ]);

        $res->assertStatus(422)->assertJson(['success' => false]);
    }

    public function test_bet_rejects_invalid_number(): void
    {
        $token  = $this->registerAndFund('9876500002', 1000);
        $slotNo = $this->withToken($token)->getJson('/api/round/current')->json('data.slot_no');

        $res = $this->withToken($token)->postJson('/api/place-bid', [
            'slot_no' => $slotNo,
            'bids'    => json_encode([['99' => 100]]),
        ]);

        $res->assertStatus(422);
    }

    public function test_withdraw_blocked_without_kyc(): void
    {
        $token = $this->registerAndFund('9876500003', 1000);
        $res   = $this->withToken($token)->postJson('/api/withdraw', ['amount' => 100]);
        $res->assertStatus(403)->assertJson(['success' => false]);
    }

    // ── J betting tests ───────────────────────────────────────────────────────

    public function test_j_bet_accepted_using_string_key(): void
    {
        $token  = $this->registerAndFund('9876500010', 1000);
        $slotNo = $this->withToken($token)->getJson('/api/round/current')->json('data.slot_no');

        $res = $this->withToken($token)->postJson('/api/place-bid', [
            'slot_no' => $slotNo,
            'bids'    => json_encode([['j' => 100]]),
        ]);

        $res->assertOk()->assertJson(['success' => true]);
        $this->assertEquals('900.00', $res->json('data.balance'));
    }

    public function test_j_does_not_win_when_j_pool_is_empty(): void
    {
        // J-Pool starts at 0 → Condition 2 fails → number wins.
        $token = $this->registerAndFund('9876500011', 10000);

        $session = $this->withToken($token)->getJson('/api/round/current')->json('data');
        $slotId  = $session['slot_id'];
        $slotNo  = $session['slot_no'];

        // Bet enough that J payout (100×20=₹2000) ≤ total bet (₹2100).
        // J-Pool is ₹0 so condition 2 fails.
        $bids = ['j' => 100, '1' => 2000];
        $this->withToken($token)->postJson('/api/place-bid', [
            'slot_no' => $slotNo,
            'bids'    => json_encode([$bids]),
        ])->assertOk();

        $round = Round::find($slotId);
        app(RoundService::class)->settle($round);
        $round->refresh();

        $this->assertFalse($round->winning_is_j);
        $this->assertNotEquals(RoundService::J, $round->winning_number); // number wins
    }

    public function test_j_does_not_win_when_payout_exceeds_total_bet(): void
    {
        // Seed J-Pool so condition 2 passes, but make J payout > total bet.
        $this->seedJPool(5000);

        $token = $this->registerAndFund('9876500012', 10000);

        $session = $this->withToken($token)->getJson('/api/round/current')->json('data');
        $slotId  = $session['slot_id'];
        $slotNo  = $session['slot_no'];

        // J bet = ₹500 → J payout = ₹10,000. Total bet = ₹500+₹100 = ₹600 < ₹10,000. Condition 1 fails.
        $bids = ['j' => 500, '1' => 100];
        $this->withToken($token)->postJson('/api/place-bid', [
            'slot_no' => $slotNo,
            'bids'    => json_encode([$bids]),
        ])->assertOk();

        $round = Round::find($slotId);
        app(RoundService::class)->settle($round);
        $round->refresh();

        $this->assertFalse($round->winning_is_j);
    }

    public function test_j_does_not_win_when_hourly_limit_reached(): void
    {
        $this->seedJPool(100000);

        // Set hourly count to max already.
        $currentHour = now()->format('Y-m-d\TH');
        Setting::put('j_hour_key',   $currentHour);
        Setting::put('j_hour_count', '3'); // already at max (3)

        $token = $this->registerAndFund('9876500013', 10000);

        $session = $this->withToken($token)->getJson('/api/round/current')->json('data');
        $slotId  = $session['slot_id'];
        $slotNo  = $session['slot_no'];

        // J bet = ₹100, total bet = ₹200 → conditions 1 & 2 pass, but condition 3 fails.
        $bids = ['j' => 100, '1' => 100];
        $this->withToken($token)->postJson('/api/place-bid', [
            'slot_no' => $slotNo,
            'bids'    => json_encode([$bids]),
        ])->assertOk();

        $round = Round::find($slotId);
        app(RoundService::class)->settle($round);
        $round->refresh();

        $this->assertFalse($round->winning_is_j);
    }

    public function test_j_delay_postpones_win_and_clears_on_target_slot(): void
    {
        $this->seedJPool(100000);

        $token = $this->registerAndFund('9876500014', 50000);

        $session = $this->withToken($token)->getJson('/api/round/current')->json('data');
        $slotId  = $session['slot_id'];
        $slotNo  = $session['slot_no'];

        // All 3 conditions pass. Delay min=max=1 so it fires on the NEXT slot.
        Setting::put('j_delay_min_rounds', '1');
        Setting::put('j_delay_max_rounds', '1');

        // J bet = ₹100, other bets = ₹2000 → total = ₹2100 > ₹2000 payout. All conditions pass.
        $bids = ['j' => 100, '1' => 2000];
        $this->withToken($token)->postJson('/api/place-bid', [
            'slot_no' => $slotNo,
            'bids'    => json_encode([$bids]),
        ])->assertOk();

        $rounds = app(RoundService::class);
        $round  = Round::find($slotId);

        // Settle round 1: conditions met → delay started → J does NOT win yet.
        $rounds->settle($round);
        $round->refresh();
        $this->assertFalse($round->winning_is_j, 'J should not win on first round (delay active)');

        // Advance to next slot.
        $nextSlotNo = $slotNo + 1;
        Carbon::setTestNow(Carbon::createFromTimestamp($nextSlotNo * $rounds->sessionSeconds()));

        // Need a fresh player (different mobile) for the next round.
        $token2 = $this->registerAndFund('9876500015', 50000);
        $session2 = $this->withToken($token2)->getJson('/api/round/current')->json('data');
        $slotId2  = $session2['slot_id'];
        $slotNo2  = $session2['slot_no'];

        $this->withToken($token2)->postJson('/api/place-bid', [
            'slot_no' => $slotNo2,
            'bids'    => json_encode([['j' => 100, '1' => 2000]]),
        ])->assertOk();

        // Advance past betting window in this new slot.
        Carbon::setTestNow(Carbon::createFromTimestamp($nextSlotNo * $rounds->sessionSeconds() + 56));

        $round2 = Round::find($slotId2);
        $rounds->settle($round2);
        $round2->refresh();

        $this->assertTrue($round2->winning_is_j, 'J should win after delay elapses');
        $this->assertEquals(RoundService::J, $rounds->serializeWinner($round2->winning_number));
    }

    public function test_j_pool_deducted_on_j_win(): void
    {
        $this->seedJPool(10000);
        Setting::put('j_delay_min_rounds', '0');
        Setting::put('j_delay_max_rounds', '0');
        Setting::put('j_pending_until_slot', '0');

        $token = $this->registerAndFund('9876500016', 50000);

        $session = $this->withToken($token)->getJson('/api/round/current')->json('data');
        $slotId  = $session['slot_id'];
        $slotNo  = $session['slot_no'];

        // J = ₹100 → payout = ₹2,000. Total = ₹2,100 (> ₹2,000). J-Pool = ₹10,000 (> ₹2,000).
        // Delay = 0 → J wins immediately.
        $this->withToken($token)->postJson('/api/place-bid', [
            'slot_no' => $slotNo,
            'bids'    => json_encode([['j' => 100, '1' => 2000]]),
        ])->assertOk();

        $jPoolBefore = HouseWallet::jPool()->balance;

        $round = Round::find($slotId);
        app(RoundService::class)->settle($round);
        $round->refresh();

        $this->assertTrue($round->winning_is_j);
        $jPoolAfterDebit = (float) HouseWallet::jPool()->fresh()->balance;
        // J-Pool debited ₹2,000 for payout; then receives 10% of net profit.
        // Net profit = 2100 - 2000 = 100. 10% = 10. So final = 10000 - 2000 + 10 = 8010.
        $this->assertEquals('8010.00', number_format($jPoolAfterDebit, 2, '.', ''));
    }

    public function test_profit_distributed_to_house_wallets_on_number_win(): void
    {
        $token = $this->registerAndFund('9876500017', 10000);

        $session = $this->withToken($token)->getJson('/api/round/current')->json('data');
        $slotId  = $session['slot_id'];
        $slotNo  = $session['slot_no'];

        // Bet ₹100 on each of 0-9 → total = ₹1000. Winner = 0 (tiebreak). Payout = ₹900.
        // Net profit = ₹100. J-Pool gets 10% (₹10), Admin gets 90% (₹90).
        $bids = [];
        for ($i = 0; $i < 10; $i++) {
            $bids[(string) $i] = 100;
        }
        $this->withToken($token)->postJson('/api/place-bid', [
            'slot_no' => $slotNo,
            'bids'    => json_encode([$bids]),
        ])->assertOk();

        $jPoolBefore    = (float) HouseWallet::jPool()->balance;
        $adminBefore    = (float) HouseWallet::adminProfit()->balance;

        $round = Round::find($slotId);
        app(RoundService::class)->settle($round);
        $round->refresh();

        $jPoolAfter  = (float) HouseWallet::jPool()->fresh()->balance;
        $adminAfter  = (float) HouseWallet::adminProfit()->fresh()->balance;

        $this->assertEquals(10.00, round($jPoolAfter - $jPoolBefore, 2));
        $this->assertEquals(90.00, round($adminAfter - $adminBefore, 2));

        $this->assertEquals('10.00', $round->j_pool_contribution);
        $this->assertEquals('90.00', $round->admin_profit_amount);
    }

    public function test_j_hourly_count_resets_across_hour_boundary(): void
    {
        $this->seedJPool(100000);
        Setting::put('j_delay_min_rounds', '0');
        Setting::put('j_delay_max_rounds', '0');

        // Set count to max for the PREVIOUS hour.
        Setting::put('j_hour_key',   '2026-06-18T09');   // previous hour
        Setting::put('j_hour_count', '3');

        $token = $this->registerAndFund('9876500018', 50000);

        $session = $this->withToken($token)->getJson('/api/round/current')->json('data');
        $slotId  = $session['slot_id'];
        $slotNo  = $session['slot_no'];

        $this->withToken($token)->postJson('/api/place-bid', [
            'slot_no' => $slotNo,
            'bids'    => json_encode([['j' => 100, '1' => 2000]]),
        ])->assertOk();

        $round = Round::find($slotId);
        app(RoundService::class)->settle($round);
        $round->refresh();

        // Hour changed → counter reset → J can win again.
        $this->assertTrue($round->winning_is_j, 'J should win in new hour even though prev hour had 3 wins');
        $this->assertEquals('1', Setting::get('j_hour_count'));
        $this->assertEquals(now()->format('Y-m-d\TH'), Setting::get('j_hour_key'));
    }

    public function test_winning_number_serialized_as_j_string(): void
    {
        $this->seedJPool(100000);
        Setting::put('j_delay_min_rounds', '0');
        Setting::put('j_delay_max_rounds', '0');

        $token = $this->registerAndFund('9876500019', 50000);

        $session = $this->withToken($token)->getJson('/api/round/current')->json('data');
        $slotId  = $session['slot_id'];
        $slotNo  = $session['slot_no'];

        $this->withToken($token)->postJson('/api/place-bid', [
            'slot_no' => $slotNo,
            'bids'    => json_encode([['j' => 100, '1' => 2000]]),
        ])->assertOk();

        // Jump past betting window.
        Carbon::setTestNow('2026-06-18 10:00:56');

        $result = $this->withToken($token)->getJson("/api/round/{$slotId}/result");
        $result->assertOk();

        // API must return integer 10, not string "j".
        $this->assertSame(RoundService::J, $result->json('data.winning_number'));
        $this->assertTrue($result->json('data.winning_is_j'));

        // Player bet on J → payout = 100 × 20 = ₹2000.
        $this->assertEquals('2000.00', $result->json('data.your_payout'));

        // Bet response shows integer 10 in bets array.
        $jBet = collect($result->json('data.bets'))->firstWhere('number', RoundService::J);
        $this->assertNotNull($jBet);
        $this->assertTrue($jBet['is_winner']);
    }

    public function test_bet_greater_than_1_lakh_accepted(): void
    {
        // Player funds ₹5,00,000 and bets ₹2,00,000 on number 1 (previously capped at 1 Lakh).
        $token  = $this->registerAndFund('9876500020', 500000);
        $slotNo = $this->withToken($token)->getJson('/api/round/current')->json('data.slot_no');

        $res = $this->withToken($token)->postJson('/api/place-bid', [
            'slot_no' => $slotNo,
            'bids'    => json_encode([['1' => 200000]]),
        ]);

        $res->assertOk()->assertJson(['success' => true]);
        $this->assertEquals('300000.00', $res->json('data.balance')); // 500,000 - 200,000
    }

    public function test_j_disabled_by_admin_never_wins(): void
    {
        $this->seedJPool(100000);
        Setting::put('j_enabled', '0'); // Admin disables J
        Setting::put('j_delay_min_rounds', '0');
        Setting::put('j_delay_max_rounds', '0');

        $token   = $this->registerAndFund('9876500021', 50000);
        $session = $this->withToken($token)->getJson('/api/round/current')->json('data');
        $slotId  = $session['slot_id'];
        $slotNo  = $session['slot_no'];

        // Bet meets all other conditions (J bet ₹100, other bets ₹2000, total ₹2100 > ₹2000)
        $this->withToken($token)->postJson('/api/place-bid', [
            'slot_no' => $slotNo,
            'bids'    => json_encode([['j' => 100, '1' => 2000]]),
        ])->assertOk();

        $round = Round::find($slotId);
        app(RoundService::class)->settle($round);
        $round->refresh();

        // Because j_enabled = 0, J must NOT win!
        $this->assertFalse($round->winning_is_j);
        $this->assertNotEquals(RoundService::J, $round->winning_number);
    }

    public function test_force_j_next_round_wins_immediately_and_resets(): void
    {
        // Force J on next round (pool is empty, no other bets, delay is default)
        Setting::put('j_force_next_round', '1');

        $token   = $this->registerAndFund('9876500022', 1000);
        $session = $this->withToken($token)->getJson('/api/round/current')->json('data');
        $slotId  = $session['slot_id'];
        $slotNo  = $session['slot_no'];

        // Only ₹50 bet on J (total bet is ₹50, payout is ₹1000, pool is ₹0).
        // In normal mode this would fail conditions 1 & 2. But forced = 1 bypasses everything!
        $this->withToken($token)->postJson('/api/place-bid', [
            'slot_no' => $slotNo,
            'bids'    => json_encode([['j' => 50]]),
        ])->assertOk();

        $round = Round::find($slotId);
        app(RoundService::class)->settle($round);
        $round->refresh();

        $this->assertTrue($round->winning_is_j);
        $this->assertEquals(RoundService::J, $round->winning_number);

        // j_force_next_round must be consumed and reset to '0'
        $this->assertEquals('0', Setting::get('j_force_next_round'));

        // Payout must be credited: 50 * 20 = 1000. Balance = 1000 - 50 + 1000 = 1950.
        $balance = $this->withToken($token)->getJson('/api/wallet/balance')->json('data.balance');
        $this->assertEquals('1950.00', $balance);
    }

    public function test_j_test_mode_bypasses_conditions(): void
    {
        // Turn on continuous QA Test Mode
        Setting::put('j_test_mode', '1');

        $token   = $this->registerAndFund('9876500023', 1000);
        $session = $this->withToken($token)->getJson('/api/round/current')->json('data');
        $slotId  = $session['slot_id'];
        $slotNo  = $session['slot_no'];

        $this->withToken($token)->postJson('/api/place-bid', [
            'slot_no' => $slotNo,
            'bids'    => json_encode([['j' => 50]]),
        ])->assertOk();

        $round = Round::find($slotId);
        app(RoundService::class)->settle($round);
        $round->refresh();

        $this->assertTrue($round->winning_is_j);
    }

    public function test_betting_with_key_10_and_history_shows_integer_10(): void
    {
        Setting::put('j_force_next_round', '1');

        $token   = $this->registerAndFund('9876500024', 2000);
        $session = $this->withToken($token)->getJson('/api/round/current')->json('data');
        $slotId  = $session['slot_id'];
        $slotNo  = $session['slot_no'];

        // Place bid using integer key "10" (as frontend sends 10)
        $this->withToken($token)->postJson('/api/place-bid', [
            'slot_no' => $slotNo,
            'bids'    => json_encode([['10' => 100]]),
        ])->assertOk();

        $round = Round::find($slotId);
        app(RoundService::class)->settle($round);
        $round->refresh();

        $this->assertTrue($round->winning_is_j);
        $this->assertSame(RoundService::J, $round->winning_number);

        // Result endpoint checks
        $result = $this->withToken($token)->getJson("/api/round/{$slotId}/result");
        $result->assertOk();
        $this->assertSame(10, $result->json('data.winning_number'));
        $this->assertSame(10, $result->json('data.bets.0.number'));

        // History endpoint checks: winning_number must be integer 10
        $historyRes = $this->withToken($token)->getJson('/api/round/history');
        $historyRes->assertOk();
        $history = $historyRes->json('data.history');
        $this->assertSame(10, $history[0]);
    }
}
