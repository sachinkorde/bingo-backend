<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Deposit;
use App\Models\Withdrawal;
use App\Services\ReferralService;
use App\Services\WalletService;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    public function balance(Request $request, WalletService $wallets)
    {
        return $this->ok(['balance' => $wallets->balance($request->user())], 'Balance');
    }

    /**
     * Deposit (add cash). In production this must be confirmed by a LICENSED
     * payment provider (UPI/crypto) before crediting — that provider plugs in
     * where the status is decided. In local/dev (no gateway) we auto-confirm
     * so the flow is testable end to end.
     */
    public function addAmount(Request $request, WalletService $wallets, ReferralService $referrals)
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
            'source' => ['nullable', 'string'],
        ]);

        $user = $request->user();

        $deposit = Deposit::create([
            'user_id' => $user->id,
            'amount' => number_format((float) $data['amount'], 2, '.', ''),
            'source' => $data['source'] ?? 'bank',
            'status' => app()->environment('production') ? 'pending' : 'success',
        ]);

        if ($deposit->status === 'success') {
            $wallets->credit($user, $deposit->amount, 'deposit', "deposit:{$deposit->id}");
            $referrals->rewardInviterOnFirstDeposit($user);
        }

        return $this->ok([
            'source' => $deposit->source,
            'amount' => (string) $deposit->amount,
            'status' => $deposit->status,
            'balance' => $wallets->balance($user),
        ], $deposit->status === 'success'
            ? 'Amount added successfully.'
            : 'Deposit initiated. Awaiting payment confirmation.');
    }

    /**
     * Withdrawal request. Requires verified KYC + bank details. Funds are held
     * (debited) now; an admin approves the actual payout via the provider.
     */
    public function withdraw(Request $request, WalletService $wallets)
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
            'method' => ['nullable', 'in:bank,upi,crypto'],
        ]);

        $user = $request->user();
        $gamer = $user->gamer;

        if (! $gamer || $gamer->kyc_status !== 'verified') {
            return $this->fail('Withdrawals require completed KYC verification.', 403);
        }

        $bank = $user->bankDetail;
        if (! $bank) {
            return $this->fail('Add your bank/UPI details before withdrawing.', 422);
        }

        // Hold the funds now (throws InsufficientBalanceException -> 422).
        $wallets->debit($user, $data['amount'], 'withdraw', null, ['method' => $data['method'] ?? 'bank']);

        $withdrawal = Withdrawal::create([
            'user_id' => $user->id,
            'amount' => number_format((float) $data['amount'], 2, '.', ''),
            'method' => $data['method'] ?? 'bank',
            'bank_detail_id' => $bank->id,
            'status' => 'pending',
        ]);

        return $this->ok([
            'withdrawal_id' => $withdrawal->id,
            'status' => $withdrawal->status,
            'balance' => $wallets->balance($user),
        ], 'Withdrawal request submitted for approval.');
    }
}
