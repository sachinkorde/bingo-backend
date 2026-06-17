<?php

namespace App\Services;

use App\Models\Bet;
use App\Models\Round;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Clock-aligned 60-second game sessions, running 24/7.
 *
 *   slot_no = floor(unixTime / session_seconds)
 *
 * Because the session is derived purely from the wall clock, EVERY device that
 * has synced the server time computes the exact same session + seconds-left
 * locally — no per-second API calls are needed.
 *
 * The winning number is fair + provably-fair: derived from a per-session secret
 * seed whose hash is published when the session is created and whose seed is
 * revealed at settlement. It does NOT depend on how players bet.
 */
class RoundService
{
    public function __construct(private WalletService $wallets) {}

    public function numbers(): int
    {
        return (int) config('game.numbers', 10);
    }

    public function sessionSeconds(): int
    {
        return max(1, (int) config('game.session_seconds', 60));
    }

    public function bettingSeconds(): int
    {
        return (int) config('game.betting_seconds', 50);
    }

    // ── Clock-aligned helpers ───────────────────────────────────────────────
    public function currentSlotNo(): int
    {
        return intdiv(now()->timestamp, $this->sessionSeconds());
    }

    public function slotStart(int $slotNo): Carbon
    {
        return Carbon::createFromTimestamp($slotNo * $this->sessionSeconds());
    }

    /**
     * The active session for "now": settles any finished-but-unsettled sessions,
     * then ensures the current session row exists.
     */
    public function currentSession(): Round
    {
        $slotNo = $this->currentSlotNo();
        $this->settleDueSessions($slotNo);

        return $this->ensureSession($slotNo);
    }

    public function ensureSession(int $slotNo): Round
    {
        return Round::firstOrCreate(
            ['slot_no' => $slotNo],
            $this->newSessionAttributes($slotNo)
        );
    }

    private function newSessionAttributes(int $slotNo): array
    {
        $start = $this->slotStart($slotNo);
        $seed = Str::random(40);

        return [
            'status' => 'betting',
            'betting_started_at' => $start,
            'betting_closes_at' => $start->copy()->addSeconds($this->bettingSeconds()),
            'server_seed' => $seed,                       // secret until settle
            'server_seed_hash' => hash('sha256', $seed),  // committed up-front
        ];
    }

    /**
     * Settle every betting session whose slot has fully elapsed.
     */
    public function settleDueSessions(int $currentSlotNo): void
    {
        Round::where('status', 'betting')
            ->where('slot_no', '<', $currentSlotNo)
            ->orderBy('slot_no')
            ->get()
            ->each(fn (Round $r) => $this->settle($r));
    }

    /**
     * Phase + timing for a session, derived from the server clock.
     * Returns: phase ('betting'|'result'), phase_seconds_left, session_seconds_left.
     */
    public function timingFor(Round $session): array
    {
        $start = $this->slotStart($session->slot_no);
        $into = now()->timestamp - $start->timestamp;
        $into = max(0, min($this->sessionSeconds(), $into));

        $betting = $this->bettingSeconds();

        if ($into < $betting) {
            return [
                'phase' => 'betting',
                'phase_seconds_left' => $betting - $into,
                'session_seconds_left' => $this->sessionSeconds() - $into,
            ];
        }

        return [
            'phase' => 'result',
            'phase_seconds_left' => $this->sessionSeconds() - $into,
            'session_seconds_left' => $this->sessionSeconds() - $into,
        ];
    }

    public function isBettingOpen(Round $session): bool
    {
        return $this->timingFor($session)['phase'] === 'betting';
    }

    /**
     * The winning number once a session is in its result phase (or settled).
     */
    public function winningNumberFor(Round $session): ?int
    {
        if ($session->status === 'settled') {
            return $session->winning_number;
        }

        return $this->isBettingOpen($session) ? null : $this->deriveWinningNumber($session);
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
