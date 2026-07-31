<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\WalletTransaction;
use Illuminate\Http\Request;

/**
 * "Refer & Earn" / "My Referrals" screens. Read-only from the API side — the
 * actual bonus payment happens in ReferralService when an invitee makes their
 * first successful deposit, not here.
 */
class ReferralController extends Controller
{
    /**
     * Summary for the "Refer & Earn" screen: the code to share, the current
     * bonus amount (admin-editable from Settings), how many people used it,
     * and how much this player has earned so far.
     */
    public function summary(Request $request)
    {
        $user = $request->user();

        $bonus = (float) Setting::get('referral_bonus', config('game.referral_bonus', 0));

        return $this->ok([
            'referral_code' => $user->referral_code,
            'referral_bonus' => number_format($bonus, 2, '.', ''),
            'total_referred' => $user->referrals()->count(),
            'total_earned' => number_format((float) $user->referralEarnings(), 2, '.', ''),
        ], 'Referral summary');
    }

    /**
     * "My Referrals" — everyone who signed up with this player's code.
     * `status` is "earned" once the inviter has actually been paid for that
     * particular invitee (their first successful deposit); "pending" until
     * then. Mirrors the exact rule in ReferralService::rewardInviterOnFirstDeposit.
     */
    public function list(Request $request)
    {
        $user = $request->user();

        // One query for every referral-bonus credit this inviter has ever
        // received, instead of one query per row below.
        $earnedInviteeIds = WalletTransaction::where('user_id', $user->id)
            ->where('type', 'referral_bonus')
            ->pluck('reference')
            ->map(fn ($ref) => (int) str_replace('referral:', '', (string) $ref))
            ->flip();

        $referrals = $user->referrals()->latest('id')->get()->map(fn ($invitee) => [
            'name' => $invitee->name ?: 'Player',
            'mobile_masked' => $this->maskMobile($invitee->mobile),
            'joined_at' => $invitee->created_at->toDateString(),
            'status' => $earnedInviteeIds->has($invitee->id) ? 'earned' : 'pending',
        ]);

        return $this->ok(['referrals' => $referrals], 'My referrals');
    }

    /** 9876543210 -> 98••••3210. Mirrors WalletController::maskMobile. */
    private function maskMobile(?string $mobile): string
    {
        if (! $mobile || strlen($mobile) < 6) {
            return '••••';
        }

        return substr($mobile, 0, 2) . str_repeat('•', strlen($mobile) - 6) . substr($mobile, -4);
    }
}
