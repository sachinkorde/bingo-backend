<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bet;
use App\Models\Round;
use App\Services\RoundService;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GameController extends Controller
{
    /**
     * Current betting round — the client syncs its wheel/timer to this instead
     * of running its own clock.
     */
    public function currentRound(RoundService $rounds)
    {
        $round = $rounds->currentRound();

        $phase = $round->status === 'betting' ? 'betting' : 'result';

        // seconds_left counts down the CURRENT phase, from the server clock, so
        // every device is synchronized.
        $target = $phase === 'betting'
            ? $round->betting_closes_at
            : $round->betting_closes_at->copy()->addSeconds($rounds->resultSeconds());

        $secondsLeft = max(0, $target->timestamp - now()->timestamp);

        return $this->ok([
            'slot_id' => $round->id,
            'slot_no' => $round->slot_no,
            'status' => $round->status,
            'phase' => $phase,                  // 'betting' or 'result'
            'seconds_left' => $secondsLeft,
            'winning_number' => $round->status === 'settled' ? $round->winning_number : null,
            'server_seed_hash' => $round->server_seed_hash,
            'numbers' => $rounds->numbers(),
            'payout_multiplier' => (int) config('game.payout_multiplier', 9),
            'betting_seconds' => (int) config('game.betting_seconds', 59),
            'spin_seconds' => (int) config('game.spin_seconds', 8),
            'result_seconds' => $rounds->resultSeconds(),
        ], 'Current round');
    }

    /**
     * Place bets for the current round.
     * Accepts the original contract: slot_id + bids = [{"10":100,"20":50}].
     */
    public function placeBid(Request $request, RoundService $rounds, WalletService $wallets)
    {
        $data = $request->validate([
            'slot_id' => ['required', 'integer'],
            'bids' => ['required'],
        ]);

        $round = $rounds->currentRound();

        if ((int) $data['slot_id'] !== $round->id) {
            return $this->fail('Betting round has changed, please retry.', 409, ['slot_id' => $round->id]);
        }
        if (! $round->isBettingOpen()) {
            return $this->fail('Betting is closed for this round.', 422);
        }

        $map = $this->parseBids($data['bids']);
        if (empty($map)) {
            return $this->fail('No valid bids provided.', 422);
        }

        $numbers = $rounds->numbers();
        $minBet = (float) config('game.min_bet', 10);
        $maxPerNumber = (float) config('game.max_bet_per_number', 100000);
        $user = $request->user();

        $total = '0';
        foreach ($map as $number => $amount) {
            if ($number < 0 || $number >= $numbers) {
                return $this->fail("Invalid number: {$number}", 422);
            }
            if ($amount < $minBet) {
                return $this->fail("Minimum bet is {$minBet}.", 422);
            }

            $existing = (float) Bet::where('round_id', $round->id)
                ->where('user_id', $user->id)
                ->where('number', $number)
                ->sum('amount');

            if (($existing + $amount) > $maxPerNumber) {
                return $this->fail("Maximum bet per number is {$maxPerNumber}.", 422);
            }

            $total = bcadd($total, number_format($amount, 2, '.', ''), 2);
        }

        // Debit + create bet rows atomically so a failure can't take the money
        // without recording the bets (throws InsufficientBalanceException -> 422).
        DB::transaction(function () use ($wallets, $user, $total, $round, $map) {
            $wallets->debit($user, $total, 'bet', "round:{$round->id}");

            foreach ($map as $number => $amount) {
                Bet::create([
                    'round_id' => $round->id,
                    'user_id' => $user->id,
                    'number' => $number,
                    'amount' => number_format($amount, 2, '.', ''),
                ]);
            }
        });

        return $this->ok([
            'slot_id' => $round->id,
            'total_bet' => $total,
            'balance' => $wallets->balance($user),
        ], 'Bets placed.');
    }

    /**
     * Result of a round (winning number + this player's payout). Reveals the
     * server seed once settled so players can verify fairness.
     */
    public function result(Request $request, int $slotId)
    {
        $round = Round::find($slotId);
        if (! $round) {
            return $this->fail('Round not found.', 404);
        }

        $user = $request->user();
        $userBets = Bet::where('round_id', $round->id)->where('user_id', $user->id)->get();

        return $this->ok([
            'slot_id' => $round->id,
            'status' => $round->status,
            'winning_number' => $round->winning_number,
            'server_seed' => $round->status === 'settled' ? $round->server_seed : null,
            'server_seed_hash' => $round->server_seed_hash,
            'your_payout' => (string) $userBets->where('is_winner', true)->sum('payout'),
            'bets' => $userBets->map(fn ($b) => [
                'number' => $b->number,
                'amount' => $b->amount,
                'is_winner' => $b->is_winner,
                'payout' => $b->payout,
            ])->values(),
        ], 'Round result');
    }

    /**
     * Last 10 settled winning numbers (for the history strip).
     */
    public function history()
    {
        $history = Round::where('status', 'settled')
            ->latest('id')
            ->take(10)
            ->pluck('winning_number');

        return $this->ok(['history' => $history], 'History');
    }

    /**
     * Normalise the bids payload into [number => amount].
     * Accepts: JSON string, [{"10":100}], {"10":100}, or [{"number":10,"amount":100}].
     */
    private function parseBids($bids): array
    {
        if (is_string($bids)) {
            $bids = json_decode($bids, true);
        }
        if (! is_array($bids)) {
            return [];
        }

        $items = array_is_list($bids) ? $bids : [$bids];
        $flat = [];

        foreach ($items as $obj) {
            if (! is_array($obj)) {
                continue;
            }

            // Support {"number":10,"amount":100} form too.
            if (isset($obj['number']) && isset($obj['amount'])) {
                $n = (int) $obj['number'];
                $a = (float) $obj['amount'];
                if ($a > 0) {
                    $flat[$n] = ($flat[$n] ?? 0) + $a;
                }
                continue;
            }

            foreach ($obj as $num => $amt) {
                if (! is_numeric($num)) {
                    continue;
                }
                $a = (float) $amt;
                if ($a > 0) {
                    $flat[(int) $num] = ($flat[(int) $num] ?? 0) + $a;
                }
            }
        }

        return $flat;
    }
}
