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

    // A session is a fixed, clock-aligned window: slot_no = floor(unixtime / session_seconds).
    'session_seconds' => (int) env('GAME_SESSION_SECONDS', 60), // full session length (24/7)
    'betting_seconds' => (int) env('GAME_BETTING_SECONDS', 55), // betting window (no separate result-hold)
    'spin_seconds' => (int) env('GAME_SPIN_SECONDS', 5),        // wheel spin = the only non-betting time
    // No dead time: session = betting (55s) + spin (5s). The next betting starts
    // immediately after the spin; the win stays shown in the Winning box.
    // result window = session_seconds - betting_seconds (e.g. 10s for spin + reveal)

    'min_bet' => (float) env('GAME_MIN_BET', 10),
    'max_bet_per_number' => (float) env('GAME_MAX_BET_PER_NUMBER', 100000),
    'max_payout_per_round' => (float) env('GAME_MAX_PAYOUT_PER_ROUND', 500000),

    'starting_balance' => (float) env('GAME_STARTING_BALANCE', 0),

    /*
    |--------------------------------------------------------------------------
    | Player-to-player transfers
    |--------------------------------------------------------------------------
    | Transfers are irreversible, so they are bounded. The daily cap limits the
    | damage from a stolen account and makes bulk value-shuffling between
    | colluding accounts obvious rather than silent.
    */
    'transfer_min' => (float) env('TRANSFER_MIN', 10),
    'transfer_max_per_day' => (float) env('TRANSFER_MAX_PER_DAY', 50000),

    // Paid to the inviter when their invitee makes a first successful deposit.
    'referral_bonus' => (float) env('REFERRAL_BONUS', 50),

    'otp_digits' => (int) env('OTP_DIGITS', 6),
    'otp_ttl_seconds' => (int) env('OTP_TTL_SECONDS', 300),
    'otp_max_attempts' => (int) env('OTP_MAX_ATTEMPTS', 5),

    /*
    | TESTING ONLY. Returns the OTP in the send-otp API response so the Unity
    | console can show it while no SMS provider is wired up.
    |
    | ⚠ This defeats OTP security completely — anyone who can call the API can
    | register ANY mobile number without owning it. Must be false (or removed)
    | before real players use the app.
    */
    'otp_debug_expose' => filter_var(env('OTP_DEBUG_EXPOSE', false), FILTER_VALIDATE_BOOLEAN),
];
