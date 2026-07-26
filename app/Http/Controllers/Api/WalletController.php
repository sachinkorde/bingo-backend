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
     * Look up a transfer recipient WITHOUT moving any money, so the player can
     * confirm who they are about to pay. Transfers are irreversible — sending
     * to a mistyped number should be catchable before it happens, not after.
     *
     * Returns masked details only: enough to recognise your friend, not enough
     * to harvest the contact details of strangers.
     */
    public function lookupRecipient(Request $request)
    {
        $data = $request->validate([
            'recipient' => ['required', 'string'],
        ]);

        $sender = $request->user();
        $recipient = $this->findRecipient($data['recipient']);

        if (! $recipient) {
            return $this->fail('No player found with that mobile, email or username.', 404);
        }
        if ($recipient->id === $sender->id) {
            return $this->fail('You cannot transfer to yourself.', 422);
        }
        if ($recipient->status !== 'active') {
            return $this->fail('That account is not active.', 422);
        }

        return $this->ok([
            'name' => $recipient->name ?: 'Player',
            'mobile_masked' => $this->maskMobile($recipient->mobile),
            'username' => $recipient->referral_code,
        ], 'Recipient found');
    }

    /**
     * Send points to another player by mobile, email or username (referral
     * code). Atomic: debits the sender and credits the recipient in one
     * transaction (both ledgered).
     */
    public function transfer(Request $request, WalletService $wallets)
    {
        $min = (float) config('game.transfer_min', 10);

        $data = $request->validate([
            // mobile, email, or username (referral code) of the receiver
            'recipient' => ['required', 'string'],
            'amount' => ['required', 'numeric', 'min:' . $min],
        ], [
            'amount.min' => "Minimum transfer is {$min}.",
        ]);

        $sender = $request->user();

        $recipient = $this->findRecipient($data['recipient']);

        if (! $recipient) {
            return $this->fail('No player found with that mobile, email or username.', 404);
        }
        if ($recipient->id === $sender->id) {
            return $this->fail('You cannot transfer to yourself.', 422);
        }
        if ($recipient->status !== 'active') {
            return $this->fail('Recipient account is not active.', 422);
        }

        $amount = number_format((float) $data['amount'], 2, '.', '');

        // Daily cap: bounds the damage from a compromised account.
        $cap = (float) config('game.transfer_max_per_day', 0);
        if ($cap > 0) {
            $sentToday = (float) Transfer::where('sender_id', $sender->id)
                ->where('status', 'completed')
                ->whereDate('created_at', now()->toDateString())
                ->sum('amount');

            if (($sentToday + (float) $amount) > $cap) {
                $left = max(0, $cap - $sentToday);

                return $this->fail(
                    "Daily transfer limit reached. You can still send {$left} today.",
                    422
                );
            }
        }

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
            'amount' => $amount,
            'recipient_name' => $recipient->name ?: 'Player',
            'recipient_masked' => $this->maskMobile($recipient->mobile),
        ], 'Transfer successful.');
    }

    /**
     * Resolve a transfer target from a mobile, an email, or a username
     * (referral code). All three are unique.
     *
     * `name` is deliberately NOT searchable: names are not unique, so a name
     * lookup could silently send someone's money to the wrong player.
     */
    private function findRecipient(string $identifier): ?User
    {
        $identifier = trim($identifier);

        return User::where(function ($query) use ($identifier) {
            $query->where('mobile', $identifier)
                ->orWhere('email', $identifier)
                ->orWhere('referral_code', strtoupper($identifier));
        })->first();
    }

    /** 9876543210 -> 98••••3210 */
    private function maskMobile(?string $mobile): string
    {
        if (! $mobile || strlen($mobile) < 6) {
            return '••••';
        }

        return substr($mobile, 0, 2) . str_repeat('•', strlen($mobile) - 6) . substr($mobile, -4);
    }
}
