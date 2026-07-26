<?php

namespace App\Services;

use App\Models\Deposit;
use Illuminate\Support\Facades\DB;

/**
 * Admin/gateway actions on deposit requests.
 *
 * In production a deposit is created as `pending` — the money has not arrived
 * until a licensed payment provider (or an admin who has verified the transfer)
 * confirms it. THIS is the only place a pending deposit turns into real money.
 *
 * Mirrors WithdrawalService: the wallet is only ever touched via WalletService,
 * so every movement lands in the ledger.
 */
class DepositService
{
    public function __construct(
        private WalletService $wallets,
        private ReferralService $referrals,
    ) {}

    /**
     * Confirm a deposit and credit the player's wallet.
     *
     * Idempotent: the status is re-read under a row lock inside the transaction,
     * so two admins double-clicking Approve (or a gateway retrying its webhook)
     * cannot credit the same deposit twice.
     */
    public function approve(Deposit $deposit, ?string $providerRef = null): bool
    {
        return DB::transaction(function () use ($deposit, $providerRef) {
            $fresh = Deposit::where('id', $deposit->id)->lockForUpdate()->first();

            if (! $fresh || $fresh->status === 'success') {
                return false;
            }

            // Mark success FIRST: ReferralService counts successful deposits to
            // decide whether this is the invitee's first one.
            $fresh->update([
                'status' => 'success',
                'provider_ref' => $providerRef ?? $fresh->provider_ref,
            ]);

            $this->wallets->credit(
                $fresh->user,
                $fresh->amount,
                'deposit',
                "deposit:{$fresh->id}",
            );

            $this->referrals->rewardInviterOnFirstDeposit($fresh->user);

            return true;
        });
    }

    /**
     * Reject a deposit. Nothing to refund — a pending deposit was never credited.
     */
    public function reject(Deposit $deposit, ?string $remark = null): bool
    {
        return DB::transaction(function () use ($deposit, $remark) {
            $fresh = Deposit::where('id', $deposit->id)->lockForUpdate()->first();

            if (! $fresh || $fresh->status !== 'pending') {
                return false;
            }

            $fresh->update([
                'status' => 'failed',
                'meta' => array_merge($fresh->meta ?? [], array_filter(['remark' => $remark])),
            ]);

            return true;
        });
    }
}
