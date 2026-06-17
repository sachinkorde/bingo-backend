<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Bet extends Model
{
    protected $fillable = [
        'round_id',
        'user_id',
        'number',
        'amount',
        'payout',
        'is_winner',
    ];

    protected $casts = [
        'number' => 'integer',
        'amount' => 'decimal:2',
        'payout' => 'decimal:2',
        'is_winner' => 'boolean',
    ];

    public function round(): BelongsTo
    {
        return $this->belongsTo(Round::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
