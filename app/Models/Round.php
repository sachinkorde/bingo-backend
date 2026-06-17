<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Round extends Model
{
    protected $fillable = [
        'slot_no',
        'status',
        'betting_started_at',
        'betting_closes_at',
        'settled_at',
        'winning_number',
        'server_seed',
        'server_seed_hash',
        'total_bet',
        'total_payout',
    ];

    protected $casts = [
        'betting_started_at' => 'datetime',
        'betting_closes_at' => 'datetime',
        'settled_at' => 'datetime',
        'winning_number' => 'integer',
        'total_bet' => 'decimal:2',
        'total_payout' => 'decimal:2',
    ];

    public function bets(): HasMany
    {
        return $this->hasMany(Bet::class);
    }

    public function isBettingOpen(): bool
    {
        return $this->status === 'betting'
            && $this->betting_closes_at !== null
            && $this->betting_closes_at->isFuture();
    }
}
