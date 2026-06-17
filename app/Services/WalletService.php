<?php

namespace App\Services;

use App\Exceptions\InsufficientBalanceException;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;

/**
 * The only place money changes. Every credit/debit happens inside a DB
 * transaction with a row lock so concurrent bets/payouts can't desync or
 * double-spend, and every change is written to the wallet_transactions ledger.
 * All arithmetic uses BCMath (string math) — never floats.
 */
class WalletService
{
    public function getOrCreate(User $user): Wallet
    {
        return $user->wallet()->firstOrCreate(
            ['user_id' => $user->id],
            ['balance' => (string) config('game.starting_balance', 0), 'bonus' => 0]
        );
    }

    public function credit(User $user, string|float $amount, string $type, ?string $reference = null, array $meta = []): WalletTransaction
    {
        $amount = $this->normalize($amount);

        return DB::transaction(function () use ($user, $amount, $type, $reference, $meta) {
            $wallet = Wallet::where('user_id', $user->id)->lockForUpdate()->firstOrFail();
            $wallet->balance = bcadd((string) $wallet->balance, $amount, 2);
            $wallet->save();

            return WalletTransaction::create([
                'user_id' => $user->id,
                'type' => $type,
                'amount' => $amount,
                'balance_after' => $wallet->balance,
                'reference' => $reference,
                'meta' => $meta ?: null,
            ]);
        });
    }

    public function debit(User $user, string|float $amount, string $type, ?string $reference = null, array $meta = []): WalletTransaction
    {
        $amount = $this->normalize($amount);

        return DB::transaction(function () use ($user, $amount, $type, $reference, $meta) {
            $wallet = Wallet::where('user_id', $user->id)->lockForUpdate()->firstOrFail();

            if (bccomp((string) $wallet->balance, $amount, 2) < 0) {
                throw new InsufficientBalanceException('Insufficient balance.');
            }

            $wallet->balance = bcsub((string) $wallet->balance, $amount, 2);
            $wallet->save();

            return WalletTransaction::create([
                'user_id' => $user->id,
                'type' => $type,
                'amount' => bcmul($amount, '-1', 2),
                'balance_after' => $wallet->balance,
                'reference' => $reference,
                'meta' => $meta ?: null,
            ]);
        });
    }

    public function balance(User $user): string
    {
        return (string) $this->getOrCreate($user)->balance;
    }

    private function normalize(string|float $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }
}
