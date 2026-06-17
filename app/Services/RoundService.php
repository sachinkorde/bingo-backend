<?php

namespace App\Services;

use App\Models\Bet;
use App\Models\Round;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Owns the round lifecycle. The winning number is FAIR and provably-fair:
 * derived from a random server seed whose hash is published when the round
 * opens and whose seed is revealed at settlement. It does NOT depend on how
 * players bet. The house profit comes from the payout math + bet caps.
 */
class RoundService
{
    public function __construct(private WalletService $wallets) {}

    public function numbers(): int
    {
        return (int) config('game.numbers', 10);
    }

    /**
     * The active betting round. Rotates automatically as time passes:
     * settles an expired round and opens the next one.
     */
    public function currentRound(): Round
    {
        $round = Round::latest('id')->first();

        if (! $round) {
            return $this->openRound(1);
        }

        if ($round->status === 'betting' && $round->betting_closes_at->isFuture()) {
            return $round;
        }

        if ($round->status === 'betting') {
            $this->settle($round);
        }

        return $this->openRound($round->slot_no + 1);
    }

    public function openRound(int $slotNo): Round
    {
        $seed = Str::random(40);

        return Round::create([
            'slot_no' => $slotNo,
            'status' => 'betting',
            'betting_started_at' => now(),
            'betting_closes_at' => now()->addSeconds((int) config('game.betting_seconds', 59)),
            'server_seed' => $seed,                        // secret until settle
            'server_seed_hash' => hash('sha256', $seed),   // published immediately
        ]);
    }

    public function settle(Round $round): Round
    {
        return DB::transaction(function () use ($round) {
            $round = Round::where('id', $round->id)->lockForUpdate()->first();

            if ($round->status === 'settled') {
                return $round;
            }

            $winning = $this->deriveWinningNumber($round);
            $multiplier = (int) config('game.payout_multiplier', 9);

            $totalBet = '0';
            $totalPayout = '0';

            foreach (Bet::where('round_id', $round->id)->with('user')->get() as $bet) {
                $totalBet = bcadd($totalBet, (string) $bet->amount, 2);

                if ((int) $bet->number === $winning) {
                    $payout = bcmul((string) $bet->amount, (string) $multiplier, 2);
                    $bet->is_winner = true;
                    $bet->payout = $payout;
                    $bet->save();

                    $this->wallets->credit($bet->user, $payout, 'payout', "round:{$round->id}", ['number' => $winning]);
                    $totalPayout = bcadd($totalPayout, $payout, 2);
                }
            }

            $round->update([
                'status' => 'settled',
                'settled_at' => now(),
                'winning_number' => $winning,
                'total_bet' => $totalBet,
                'total_payout' => $totalPayout,
            ]);

            return $round;
        });
    }

    /**
     * Fair winner derived from the committed secret seed — independent of bets.
     */
    public function deriveWinningNumber(Round $round): int
    {
        $hash = hash('sha256', $round->server_seed . ':' . $round->slot_no);

        return (int) (hexdec(substr($hash, 0, 8)) % $this->numbers());
    }
}
