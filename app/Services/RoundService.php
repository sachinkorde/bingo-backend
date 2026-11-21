<?php

namespace App\Services;

use App\Models\Bet;
use App\Models\Round;
use App\Models\Setting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Clock-aligned 60-second game sessions with 11 betting options (0–9 and J).
 *
 *   slot_no = floor(unixTime / session_seconds)
 *
 * J (Joker) is stored as integer 10 in the database and serialised as the
 * string "j" in all API responses. It pays 20× and is funded by the J-Pool
 * wallet. Numbers 0–9 pay payout_multiplier× (default 9×) from round income.
 *
 * Net profit after each round is split between J-Pool (j_pool_pct %) and
 * Admin Profit (remainder), regardless of whether J or a number won.
 */
class RoundService
{
    /** Internal sentinel for the J betting option. */
    public const J = 10;

    public function __construct(
        private WalletService     $wallets,
        private HouseWalletService $houseWallets,
    ) {}

    public function numbers(): int
    {
        return (int) config('game.numbers', 11); // 0-9 + J=10
    }

    public function sessionSeconds(): int
    {
        return max(1, (int) config('game.session_seconds', 60));
    }

    public function bettingSeconds(): int
    {
        return (int) config('game.betting_seconds', 55);
    }

    // ── Clock-aligned helpers ────────────────────────────────────────────────

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

        $session = $this->ensureSession($slotNo);

        $this->settleIfBettingClosed($session);

        return $session->refresh();
    }

    /** Settle a session as soon as its betting window has ended. */
    public function settleIfBettingClosed(Round $session): void
    {
        if ($session->status === 'betting' && ! $this->isBettingOpen($session)) {
            $this->settle($session);
        }
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
        $seed  = Str::random(40);

        return [
            'status'            => 'betting',
            'betting_started_at'=> $start,
            'betting_closes_at' => $start->copy()->addSeconds($this->bettingSeconds()),
            'server_seed'       => $seed,
            'server_seed_hash'  => hash('sha256', $seed),
        ];
    }

    public function settleDueSessions(int $currentSlotNo): void
    {
        Round::where('status', 'betting')
            ->where('slot_no', '<', $currentSlotNo)
            ->orderBy('slot_no')
            ->get()
            ->each(fn (Round $r) => $this->settle($r));
    }

    // ── Timing helpers ───────────────────────────────────────────────────────

    public function timingFor(Round $session): array
    {
        $start = $this->slotStart($session->slot_no);
        $into  = now()->timestamp - $start->timestamp;
        $into  = max(0, min($this->sessionSeconds(), $into));

        $betting = $this->bettingSeconds();

        if ($into < $betting) {
            return [
                'phase'               => 'betting',
                'phase_seconds_left'  => $betting - $into,
                'session_seconds_left'=> $this->sessionSeconds() - $into,
            ];
        }

        return [
            'phase'               => 'result',
            'phase_seconds_left'  => $this->sessionSeconds() - $into,
            'session_seconds_left'=> $this->sessionSeconds() - $into,
        ];
    }

    public function isBettingOpen(Round $session): bool
    {
        return $this->timingFor($session)['phase'] === 'betting';
    }

    /**
     * The winning value once settled: returns "j" (string) or an integer (0-9),
     * or null while still in the result phase before settlement.
     */
    public function winningNumberFor(Round $session): string|int|null
    {
        if ($session->status === 'settled') {
            return $this->serializeWinner($session->winning_number);
        }

        // Determined at settlement time (depends on bets) — unknown until settle() runs.
        return null;
    }

    // ── Settlement ───────────────────────────────────────────────────────────

    public function settle(Round $round): Round
    {
        return DB::transaction(function () use ($round) {
            $round = Round::where('id', $round->id)->lockForUpdate()->first();

            if ($round->status === 'settled') {
                return $round;
            }

            // Fetch all bets once — reused for J check, payout, and profit.
            $bets = Bet::where('round_id', $round->id)->with('user')->get();

            // Sum total bet across all options (including J).
            $totalBet = '0';
            foreach ($bets as $bet) {
                $totalBet = bcadd($totalBet, (string) $bet->amount, 2);
            }

            // Determine winner.
            $jWins   = $this->shouldJWin($bets, $totalBet, $round->slot_no);
            $winning = $jWins ? self::J : $this->selectWinningNumber($bets);

            $multiplier  = $jWins
                ? (int) config('game.j_multiplier', 20)
                : (int) config('game.payout_multiplier', 9);

            $totalPayout = '0';

            // Pay winners.
            foreach ($bets as $bet) {
                if ((int) $bet->number === $winning) {
                    $payout          = bcmul((string) $bet->amount, (string) $multiplier, 2);
                    $bet->is_winner  = true;
                    $bet->payout     = $payout;
                    $bet->save();

                    $this->wallets->credit(
                        $bet->user, $payout, 'payout',
                        "round:{$round->id}",
                        ['number' => $this->serializeWinner($winning)]
                    );
                    $totalPayout = bcadd($totalPayout, $payout, 2);
                }
            }

            // J win: debit J-Pool for payout and track hourly count.
            if ($jWins) {
                $this->houseWallets->debit(
                    'j_pool', $totalPayout, 'j_payout',
                    "round:{$round->id}",
                    ['round_total_bet' => $totalBet]
                );
                $this->incrementHourlyJCount();
            }

            // Distribute net profit to house wallets (applies in all cases).
            [$jContrib, $adminContrib] = $this->distributeRoundProfit($round, $totalBet, $totalPayout);

            $round->update([
                'status'              => 'settled',
                'settled_at'          => now(),
                'winning_number'      => $winning,
                'winning_is_j'        => $jWins,
                'total_bet'           => $totalBet,
                'total_payout'        => $totalPayout,
                'j_pool_contribution' => $jContrib,
                'admin_profit_amount' => $adminContrib,
            ]);

            return $round;
        });
    }

    // ── J-win logic ──────────────────────────────────────────────────────────

    /**
     * Returns true if J should win this round.
     * Checks all 3 conditions AND applies the random anti-prediction delay.
     */
    protected function shouldJWin(Collection $bets, string $totalBet, int $slotNo): bool
    {
        // Sum all J bets.
        $jBetTotal = '0';
        foreach ($bets->where('number', self::J) as $bet) {
            $jBetTotal = bcadd($jBetTotal, (string) $bet->amount, 2);
        }

        // If nobody bet on J, skip all checks.
        if (bccomp($jBetTotal, '0', 2) <= 0) {
            return false;
        }

        $jPayout  = bcmul($jBetTotal, '20', 2);
        $jBalance = $this->houseWallets->balance('j_pool');

        // Condition 1: J payout ≤ total round bets
        if (bccomp($jPayout, $totalBet, 2) > 0) {
            return false;
        }

        // Condition 2: J payout ≤ J-Pool balance
        if (bccomp($jPayout, $jBalance, 2) > 0) {
            return false;
        }

        // Condition 3: hourly J count < max_j_per_hour
        if (! $this->hourlyJSlotAvailable()) {
            return false;
        }

        // All 3 pass → apply random delay (anti-prediction mechanism).
        return $this->resolveJDelay($slotNo);
    }

    /**
     * Manage the random 2–5 round delay before declaring J.
     * Returns true only when the delay has elapsed.
     */
    protected function resolveJDelay(int $slotNo): bool
    {
        $pendingUntil = (int) Setting::get('j_pending_until_slot', 0);

        if ($pendingUntil === 0) {
            // No delay active: start one now.
            $minDelay = (int) Setting::get('j_delay_min_rounds', 2);
            $maxDelay = (int) Setting::get('j_delay_max_rounds', 5);
            $delay    = random_int(
                min($minDelay, $maxDelay),
                max($minDelay, $maxDelay)
            );

            if ($delay <= 0) {
                return true;
            }

            Setting::put('j_pending_until_slot', (string) ($slotNo + $delay));

            return false;
        }

        if ($slotNo >= $pendingUntil) {
            // Delay has elapsed → declare J and clear the pending marker.
            Setting::put('j_pending_until_slot', '0');

            return true;
        }

        // Still within the delay window.
        return false;
    }

    /**
     * Check whether the hourly J limit allows another J win.
     * Does NOT modify state — call incrementHourlyJCount() on actual win.
     */
    protected function hourlyJSlotAvailable(): bool
    {
        $currentHourKey = now()->format('Y-m-d\TH');
        $storedHourKey  = (string) Setting::get('j_hour_key', '');
        $count          = (int) Setting::get('j_hour_count', 0);
        $maxPerHour     = (int) Setting::get('j_max_per_hour', 3);

        // Different hour → count effectively resets on next win.
        if ($storedHourKey !== $currentHourKey) {
            return true; // count will become 1 for the new hour
        }

        return $count < $maxPerHour;
    }

    /** Record a J win in the hourly counter. Called only when J actually wins. */
    protected function incrementHourlyJCount(): void
    {
        $currentHourKey = now()->format('Y-m-d\TH');
        $storedHourKey  = (string) Setting::get('j_hour_key', '');
        $count          = ($storedHourKey === $currentHourKey)
            ? (int) Setting::get('j_hour_count', 0)
            : 0;

        Setting::put('j_hour_key',   $currentHourKey);
        Setting::put('j_hour_count', (string) ($count + 1));
    }

    // ── Profit distribution ──────────────────────────────────────────────────

    /**
     * Distribute net round profit to J-Pool and Admin Profit wallets.
     * Returns [j_pool_share, admin_share] both as BCMath strings.
     */
    protected function distributeRoundProfit(Round $round, string $totalBet, string $totalPayout): array
    {
        $netProfit = bcsub($totalBet, $totalPayout, 2);

        if (bccomp($netProfit, '0', 2) <= 0) {
            return ['0.00', '0.00'];
        }

        $jPct    = bcdiv((string) Setting::get('j_pool_pct', 10), '100', 6);
        $jShare  = bcmul($netProfit, $jPct, 2);
        $aShare  = bcsub($netProfit, $jShare, 2);

        $this->houseWallets->credit('j_pool',       $jShare, 'profit_share', "round:{$round->id}");
        $this->houseWallets->credit('admin_profit',  $aShare, 'profit_share', "round:{$round->id}");

        return [$jShare, $aShare];
    }

    // ── Number selection (0–9 fallback) ─────────────────────────────────────

    /**
     * House-safe winning number from 0–9: picks the number with the least
     * total amount wagered. Zero-bet numbers cost nothing → always preferred.
     * Tiebreak: lowest index.
     */
    protected function selectWinningNumber(Collection $bets): int
    {
        // Always evaluate 0–9 only; J (10) is handled separately.
        $totals = array_fill(0, 10, '0.00');

        foreach ($bets as $bet) {
            $n = (int) $bet->number;
            if ($n >= 10) {
                continue; // skip J bets
            }
            $totals[$n] = bcadd($totals[$n], (string) $bet->amount, 2);
        }

        $min        = min($totals);
        $candidates = array_keys(array_filter($totals, fn ($v) => $v == $min));
        sort($candidates);

        return $candidates[0];
    }

    // ── Serialization helpers ────────────────────────────────────────────────

    /**
     * Convert internal integer winner (10 = J) to the API-facing value.
     * Returns "j" for J, or the integer for 0-9, or null.
     */
    public function serializeWinner(int|null $number): string|int|null
    {
        if ($number === null) {
            return null;
        }

        return $number === self::J ? 'j' : $number;
    }

    /**
     * Convert API-facing bid key ("j" or "0".."9") to internal integer.
     * Returns null if the value is not a recognised option.
     */
    public function parseBidKey(string $key): ?int
    {
        if (strtolower(trim($key)) === 'j') {
            return self::J;
        }

        if (is_numeric($key)) {
            return (int) $key;
        }

        return null;
    }

    // ── Legacy / reference ───────────────────────────────────────────────────

    /**
     * @deprecated No longer used for settlement.
     * Kept for reference only (provably-fair seed verification).
     */
    public function deriveWinningNumber(Round $round): int
    {
        $hash = hash('sha256', $round->server_seed . ':' . $round->slot_no);

        return (int) (hexdec(substr($hash, 0, 8)) % 10); // mod 10, not 11
    }
}
