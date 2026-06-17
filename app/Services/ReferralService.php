<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\User;
use App\Models\WalletTransaction;

/**
 * Referral rewards. The inviter is paid only when their invitee makes a FIRST
 * successful deposit (not at signup) — this prevents fake-signup farming.
 */
class ReferralService
{
    public function __construct(private WalletService $wallets) {}

    public function rewardInviterOnFirstDeposit(User $invitee): void
    {
        // Amount is set by admins from the dashboard (falls back to config).
        $bonus = (float) Setting::get('referral_bonus', config('game.referral_bonus', 0));

        if ($bonus <= 0 || empty($invitee->referred_by)) {
            return;
        }

        // Only on the invitee's first successful deposit.
        if ($invitee->deposits()->where('status', 'success')->count() !== 1) {
            return;
        }

        $inviter = User::where('referral_code', $invitee->referred_by)->first();
        if (! $inviter) {
            return;
        }

        // Idempotency guard — never reward twice for the same invitee.
        $already = WalletTransaction::where('user_id', $inviter->id)
            ->where('type', 'referral_bonus')
            ->where('reference', "referral:{$invitee->id}")
            ->exists();

        if ($already) {
            return;
        }

        $this->wallets->credit($inviter, $bonus, 'referral_bonus', "referral:{$invitee->id}", [
            'invitee_id' => $invitee->id,
            'invitee_mobile' => $invitee->mobile,
        ]);
    }
}
