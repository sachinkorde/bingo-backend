<?php

namespace App\Services;

use App\Models\Withdrawal;

/**
 * Admin actions on withdrawal requests. Funds are already held (debited) when
 * the player requests; approval marks it paid (provider payout happens via the
 * adapter), rejection refunds the held amount back to the wallet.
 */
class WithdrawalService
{
    public function __construct(private WalletService $wallets) {}

    public function approve(Withdrawal $withdrawal, ?int $adminId = null, ?string $providerRef = null): void
    {
        if ($withdrawal->status !== 'pending') {
            return;
        }

        // TODO: trigger the licensed payment provider payout here, then mark paid.
        $withdrawal->update([
            'status' => 'paid',
            'processed_by' => $adminId,
            'provider_ref' => $providerRef,
        ]);
    }

    public function reject(Withdrawal $withdrawal, ?int $adminId = null, ?string $remark = null): void
    {
        if ($withdrawal->status !== 'pending') {
            return;
        }

        // Refund the held funds.
        $this->wallets->credit($withdrawal->user, $withdrawal->amount, 'refund', "withdrawal:{$withdrawal->id}");

        $withdrawal->update([
            'status' => 'rejected',
            'processed_by' => $adminId,
            'remark' => $remark,
        ]);
    }
}
