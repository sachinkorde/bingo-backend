<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HouseWalletTransaction extends Model
{
    protected $fillable = [
        'wallet_type',
        'amount',
        'balance_after',
        'type',
        'reference',
        'meta',
    ];

    protected $casts = [
        'amount'        => 'decimal:2',
        'balance_after' => 'decimal:2',
        'meta'          => 'array',
    ];
}
