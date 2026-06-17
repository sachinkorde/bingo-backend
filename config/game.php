<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Real Bingo game rules (server-authoritative)
    |--------------------------------------------------------------------------
    | The house edge comes from the payout math (e.g. 9x on 10 numbers ≈ 10%
    | edge with FAIR RNG) — not from rigging. Bet/payout caps bound the
    | worst-case loss on any single round (like a casino table limit).
    */

    'numbers' => (int) env('GAME_NUMBERS', 10),                 // slots 0..9
    'payout_multiplier' => (int) env('GAME_PAYOUT_MULTIPLIER', 9),

    'betting_seconds' => (int) env('GAME_BETTING_SECONDS', 59), // betting window
    'spin_seconds' => (int) env('GAME_SPIN_SECONDS', 8),        // wheel animation time

    'min_bet' => (float) env('GAME_MIN_BET', 10),
    'max_bet_per_number' => (float) env('GAME_MAX_BET_PER_NUMBER', 100000),
    'max_payout_per_round' => (float) env('GAME_MAX_PAYOUT_PER_ROUND', 500000),

    'starting_balance' => (float) env('GAME_STARTING_BALANCE', 0),

    // Paid to the inviter when their invitee makes a first successful deposit.
    'referral_bonus' => (float) env('REFERRAL_BONUS', 50),

    'otp_ttl_seconds' => (int) env('OTP_TTL_SECONDS', 300),
    'otp_max_attempts' => (int) env('OTP_MAX_ATTEMPTS', 5),
];
