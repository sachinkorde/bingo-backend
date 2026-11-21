<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HouseWallet extends Model
{
    protected $fillable = ['type', 'balance'];

    protected $casts = [
        'balance' => 'decimal:2',
    ];

    public static function jPool(): ?self
    {
        return static::where('type', 'j_pool')->first();
    }

    public static function adminProfit(): ?self
    {
        return static::where('type', 'admin_profit')->first();
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(HouseWalletTransaction::class, 'wallet_type', 'type');
    }
}
