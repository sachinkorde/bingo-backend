<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Gamer extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'gender',
        'date_of_birth',
        'address',
        'adhar_number',
        'pan_number',
        'adhar_document',
        'pan_document',
        'profile_image',
        'kyc_status',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isKycVerified(): bool
    {
        return $this->kyc_status === 'verified';
    }
}
