<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bet;
use App\Models\Round;
use App\Models\Setting;
use App\Services\RoundService;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GameController extends Controller
{
    /**
     * Current clock-aligned session. The client syncs `server_time` ONCE and then
     * counts locally — every device shows the same second with no per-second calls.
     */
    public function currentRound(RoundService $rounds)
    {
        $session = $rounds->currentSession();
        $t       = $rounds->timingFor($session);

        return $this->ok([
            'server_time'       => now()->valueOf(),   // ms epoch — sync once, count locally
            'slot_id'           => $session->id,
            'slot_no'           => $session->slot_no,
            'status'            => $session->status,
            'phase'             => $t['phase'],        // 'betting' or 'result'
            'seconds_left'      => $t['phase_seconds_left'],
            'session_seconds_left' => $t['session_seconds_left'],
            'winning_number'    => $rounds->winningNumberFor($session), // "j", 0-9, or null
            'server_seed_hash'  => $session->server_seed_hash,
            'numbers'           => $rounds->numbers(),
            'payout_multiplier' => (int) config('game.payout_multiplier', 9),
            'j_multiplier'      => (int) config('game.j_multiplier', 20),
            'has_j'             => true,
            'session_seconds'   => $rounds->sessionSeconds(),
            'betting_seconds'   => $rounds->bettingSeconds(),
            'spin_seconds'      => (int) config('game.spin_seconds', 5),
        ], 'Current session');
    }

    /**
     * Lightweight public time/session sync (used by the dashboard corner timer).
     */
    public function serverTime(RoundService $rounds)
    {
        $session = $rounds->currentSession();
        $t       = $rounds->timingFor($session);

        return $this->ok([
            'server_time'         => now()->valueOf(),
            'slot_no'             => $session->slot_no,
            'phase'               => $t['phase'],
            'seconds_left'        => $t['phase_seconds_left'],
            'session_seconds_left'=> $t['session_seconds_left'],
            'session_seconds'     => $rounds->sessionSeconds(),
            'betting_seconds'     => $rounds->bettingSeconds(),
        ], 'Server time');
    }

    /**
     * Place bets for the current round.
     * Accepts bid keys as numeric strings OR "j" for the Joker option.
     * Bids format: [{"j": 100, "0": 50}] or [{"number":"j","amount":100}].
     */
    public function placeBid(Request $request, RoundService $rounds, WalletService $wallets)
    {
        $data = $request->validate([
            'slot_no' => ['required', 'integer'],
            'bids'    => ['required'],
        ]);

        $round = $rounds->currentSession();

        if ((string) $data['slot_no'] !== (string) $round->slot_no) {
            return $this->fail('Betting round has changed, please retry.', 409, ['slot_no' => $round->slot_no]);
        }
        if (! $rounds->isBettingOpen($round)) {
            return $this->fail('Betting is closed for this round.', 422);
        }

        $map = $this->parseBids($data['bids'], $rounds);
        if (empty($map)) {
            return $this->fail('No valid bids provided.', 422);
        }

        $minBet       = (float) config('game.min_bet', 10);
        $maxSetting   = Setting::get('max_bet_per_number');
        $maxPerNumber = $maxSetting !== null ? (float) $maxSetting : (float) config('game.max_bet_per_number', 1000000);
        $user         = $request->user();

        $total = '0';
        foreach ($map as $number => $amount) {
            // 0-9 and J=10 are valid; anything else is rejected.
            if ($number < 0 || $number > RoundService::J) {
                $label = $number === RoundService::J ? 'J' : $number;
                return $this->fail("Invalid number: {$label}", 422);
            }
            if ($amount < $minBet) {
                return $this->fail("Minimum bet is {$minBet}.", 422);
            }

            $existing = (float) Bet::where('round_id', $round->id)
                ->where('user_id', $user->id)
                ->where('number', $number)
                ->sum('amount');

            if ($maxPerNumber > 0 && ($existing + $amount) > $maxPerNumber) {
                return $this->fail("Maximum bet per number is {$maxPerNumber}.", 422);
            }

            $total = bcadd($total, number_format($amount, 2, '.', ''), 2);
        }

        DB::transaction(function () use ($wallets, $user, $total, $round, $map) {
            $wallets->debit($user, $total, 'bet', "round:{$round->id}");

            foreach ($map as $number => $amount) {
                Bet::create([
                    'round_id' => $round->id,
                    'user_id'  => $user->id,
                    'number'   => $number,
                    'amount'   => number_format($amount, 2, '.', ''),
                ]);
            }
        });

        return $this->ok([
            'slot_id'   => $round->id,
            'total_bet' => $total,
            'balance'   => $wallets->balance($user),
        ], 'Bets placed.');
    }

    /**
     * Result of a round. Returns "j" as winning_number when Joker won.
     */
    public function result(Request $request, RoundService $rounds, int $slotId)
    {
        $round = Round::find($slotId);
        if (! $round) {
            return $this->fail('Round not found.', 404);
        }

        $rounds->settleIfBettingClosed($round);
        $round->refresh();

        $user     = $request->user();
        $userBets = Bet::where('round_id', $round->id)->where('user_id', $user->id)->get();

        return $this->ok([
            'slot_id'        => $round->id,
            'status'         => $round->status,
            'winning_number' => $rounds->serializeWinner($round->winning_number),  // "j" or 0-9 or null
            'winning_is_j'   => $round->winning_is_j ?? false,
            'server_seed'    => $round->status === 'settled' ? $round->server_seed : null,
            'server_seed_hash'=> $round->server_seed_hash,
            'your_payout'    => number_format((float) $userBets->where('is_winner', true)->sum('payout'), 2, '.', ''),
            'bets'           => $userBets->map(fn ($b) => [
                'number'    => $rounds->serializeWinner($b->number),   // "j" or 0-9
                'amount'    => $b->amount,
                'is_winner' => $b->is_winner,
                'payout'    => $b->payout,
            ])->values(),
        ], 'Round result');
    }

    /**
     * Last 10 settled winning numbers (for the history strip).
     * Returns "j" for Joker wins.
     */
    public function history(RoundService $rounds)
    {
        $history = Round::where('status', 'settled')
            ->latest('id')
            ->take(10)
            ->get(['winning_number'])
            ->map(fn ($r) => $rounds->serializeWinner($r->winning_number))
            ->values();

        return $this->ok(['history' => $history], 'History');
    }

    /**
     * Normalise the bids payload into [number => amount].
     * Accepts "j" or numeric keys; converts "j" → RoundService::J (10).
     */
    private function parseBids($bids, RoundService $rounds): array
    {
        if (is_string($bids)) {
            $bids = json_decode($bids, true);
        }
        if (! is_array($bids)) {
            return [];
        }

        $items = array_is_list($bids) ? $bids : [$bids];
        $flat  = [];

        foreach ($items as $obj) {
            if (! is_array($obj)) {
                continue;
            }

            // Support {"number":"j","amount":100} or {"number":5,"amount":50}.
            if (isset($obj['number']) && isset($obj['amount'])) {
                $n = $rounds->parseBidKey((string) $obj['number']);
                $a = (float) $obj['amount'];
                if ($n !== null && $a > 0) {
                    $flat[$n] = ($flat[$n] ?? 0) + $a;
                }
                continue;
            }

            // Support {"j":100,"0":50} flat map.
            foreach ($obj as $key => $amt) {
                $n = $rounds->parseBidKey((string) $key);
                $a = (float) $amt;
                if ($n !== null && $a > 0) {
                    $flat[$n] = ($flat[$n] ?? 0) + $a;
                }
            }
        }

        return $flat;
    }
}
