<?php

namespace App\Services;

use App\Models\HouseWallet;
use App\Models\HouseWalletTransaction;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Authoritative service for J-Pool and Admin-Profit wallets.
 * Uses BCMath for precision and row-level locking to prevent races.
 */
class HouseWalletService
{
    public function balance(string $type): string
    {
        return (string) (HouseWallet::where('type', $type)->value('balance') ?? '0.00');
    }

    /**
     * Credit a house wallet and record the ledger entry.
     */
    public function credit(
        string $type,
        string $amount,
        string $txType,
        ?string $reference = null,
        array $meta = []
    ): HouseWalletTransaction {
        $amount = $this->normalize($amount);

        return DB::transaction(function () use ($type, $amount, $txType, $reference, $meta) {
            $wallet = HouseWallet::where('type', $type)->lockForUpdate()->firstOrFail();
            $wallet->balance = bcadd((string) $wallet->balance, $amount, 2);
            $wallet->save();

            return HouseWalletTransaction::create([
                'wallet_type'   => $type,
                'amount'        => $amount,
                'balance_after' => $wallet->balance,
                'type'          => $txType,
                'reference'     => $reference,
                'meta'          => $meta ?: null,
            ]);
        });
    }

    /**
     * Debit a house wallet. Throws if the balance would go negative.
     */
    public function debit(
        string $type,
        string $amount,
        string $txType,
        ?string $reference = null,
        array $meta = []
    ): HouseWalletTransaction {
        $amount = $this->normalize($amount);

        return DB::transaction(function () use ($type, $amount, $txType, $reference, $meta) {
            $wallet = HouseWallet::where('type', $type)->lockForUpdate()->firstOrFail();

            if (bccomp((string) $wallet->balance, $amount, 2) < 0) {
                throw new RuntimeException("Insufficient {$type} balance.");
            }

            $wallet->balance = bcsub((string) $wallet->balance, $amount, 2);
            $wallet->save();

            return HouseWalletTransaction::create([
                'wallet_type'   => $type,
                'amount'        => bcmul($amount, '-1', 2),
                'balance_after' => $wallet->balance,
                'type'          => $txType,
                'reference'     => $reference,
                'meta'          => $meta ?: null,
            ]);
        });
    }

    private function normalize(string $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }
}
