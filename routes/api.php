<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BankController;
use App\Http\Controllers\Api\GameController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\WalletController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API routes (auto-prefixed with /api)
|--------------------------------------------------------------------------
*/

// ── Public ───────────────────────────────────────────────────────────────
// Lightweight clock sync (no auth) — the app/dashboard call this ONCE then count
// locally, so the timer is identical on every device with no per-second traffic.
Route::get('server-time', [GameController::class, 'serverTime']);

Route::post('register', [AuthController::class, 'register'])->middleware('throttle:10,1');
Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1');
Route::post('send-otp', [AuthController::class, 'sendOtp'])->middleware('throttle:6,1');
Route::post('forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:10,1');

// ── Authenticated ─────────────────────────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {
    // Auth
    Route::post('reset-password', [AuthController::class, 'resetPassword']);
    Route::post('logout', [AuthController::class, 'logout']);
    Route::post('logout-all', [AuthController::class, 'logoutAll']);

    // Profile
    Route::get('profile', [ProfileController::class, 'show']);
    Route::post('profile', [ProfileController::class, 'update']);

    // Bank details
    Route::get('bank-detail', [BankController::class, 'show']);
    Route::post('bank-detail', [BankController::class, 'update']);

    // Wallet
    Route::get('wallet/balance', [WalletController::class, 'balance']);
    Route::post('add-amount', [WalletController::class, 'addAmount']);
    Route::post('withdraw', [WalletController::class, 'withdraw']);
    Route::post('transfer', [WalletController::class, 'transfer']);

    // Game
    Route::get('round/current', [GameController::class, 'currentRound']);
    Route::get('round/history', [GameController::class, 'history']);
    Route::get('round/{slotId}/result', [GameController::class, 'result']);
    Route::post('place-bid', [GameController::class, 'placeBid']);
});
