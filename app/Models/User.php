<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

// NOTE: players are NOT dashboard users. Dashboard access is the Admin model
// (admins table + 'admin' guard). See App\Models\Admin.
class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'mobile',
        'email',
        'password',
        'role',
        'status',
        'referral_code',
        'referred_by',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // ── Relationships ────────────────────────────────────────────────
    public function gamer(): HasOne
    {
        return $this->hasOne(Gamer::class);
    }

    public function wallet(): HasOne
    {
        return $this->hasOne(Wallet::class);
    }

    public function bankDetail(): HasOne
    {
        return $this->hasOne(BankDetail::class);
    }

    public function walletTransactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class);
    }

    public function bets(): HasMany
    {
        return $this->hasMany(Bet::class);
    }

    public function deposits(): HasMany
    {
        return $this->hasMany(Deposit::class);
    }

    public function withdrawals(): HasMany
    {
        return $this->hasMany(Withdrawal::class);
    }

    // ── Helpers ──────────────────────────────────────────────────────
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    // ── Win/loss statistics (a "session" = one round the player bet in) ──────
    public function roundsPlayed(): int
    {
        return (int) $this->bets()->distinct('round_id')->count('round_id');
    }

    public function roundsWon(): int
    {
        return (int) $this->bets()->where('is_winner', true)->distinct('round_id')->count('round_id');
    }

    public function winRate(): float
    {
        $played = $this->roundsPlayed();

        return $played > 0 ? round($this->roundsWon() / $played * 100, 1) : 0.0;
    }

    public function totalWagered(): string
    {
        return (string) $this->bets()->sum('amount');
    }

    public function totalWon(): string
    {
        return (string) $this->bets()->where('is_winner', true)->sum('payout');
    }

    public function netProfit(): string
    {
        // Positive = player is up (house down); negative = player is down.
        return bcsub($this->totalWon(), $this->totalWagered(), 2);
    }

    // ── Suspicious-activity heuristics (tune thresholds as needed) ──────────
    public function suspicionReason(): ?string
    {
        // Winning far above chance over a meaningful sample.
        if ($this->roundsPlayed() >= 20 && $this->winRate() > 55) {
            return 'High win rate (' . $this->winRate() . '% over ' . $this->roundsPlayed() . ' rounds)';
        }

        // Player is up a lot (possible exploit/collusion — review).
        if ((float) $this->netProfit() > 50000) {
            return 'Large net winner (₹' . number_format((float) $this->netProfit()) . ')';
        }

        return null;
    }

    public function isSuspicious(): bool
    {
        return $this->suspicionReason() !== null;
    }
}
