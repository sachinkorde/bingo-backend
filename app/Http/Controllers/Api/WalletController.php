<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Deposit;
use App\Models\Transfer;
use App\Models\User;
use App\Models\Withdrawal;
use App\Services\ReferralService;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

    /**
     * Send money to another player by mobile or email. Atomic: debits the
     * sender and credits the recipient in one transaction (both ledgered).
     */
    public function transfer(Request $request, WalletService $wallets)
    {
        $data = $request->validate([
            'recipient' => ['required', 'string'], // mobile or email of the receiver
            'amount' => ['required', 'numeric', 'min:1'],
        ]);

        $sender = $request->user();

        $recipient = User::where('mobile', $data['recipient'])
            ->orWhere('email', $data['recipient'])
            ->first();

        if (! $recipient) {
            return $this->fail('Recipient not found.', 404);
        }
        if ($recipient->id === $sender->id) {
            return $this->fail('You cannot transfer to yourself.', 422);
        }
        if ($recipient->status !== 'active') {
            return $this->fail('Recipient account is not active.', 422);
        }

        $amount = number_format((float) $data['amount'], 2, '.', '');

        // Atomic: if the sender lacks funds, debit() throws and the whole
        // transaction (including the transfer row) rolls back.
        $transfer = DB::transaction(function () use ($sender, $recipient, $amount, $wallets) {
            $t = Transfer::create([
                'sender_id' => $sender->id,
                'recipient_id' => $recipient->id,
                'amount' => $amount,
                'status' => 'completed',
            ]);

            $wallets->debit($sender, $amount, 'transfer_out', "transfer:{$t->id}", ['to' => $recipient->mobile]);
            $wallets->credit($recipient, $amount, 'transfer_in', "transfer:{$t->id}", ['from' => $sender->mobile]);

            return $t;
        });

        return $this->ok([
            'transfer_id' => $transfer->id,
            'balance' => $wallets->balance($sender),
        ], 'Transfer successful.');
    }
}
