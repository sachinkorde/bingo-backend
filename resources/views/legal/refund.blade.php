{{--
    ⚠ DRAFT — REQUIRES LEGAL REVIEW BEFORE LAUNCH.
    Payment gateways in India generally require a published refund policy
    before approving a merchant account, and may have their own mandatory
    wording. Placeholders in [SQUARE BRACKETS] must be completed and a
    qualified lawyer (and your payment provider) must review this before it
    is relied on.
--}}
@extends('layouts.site')

@section('title', 'Refund Policy — The Real Bingo')
@section('meta_description', 'When deposits and withdrawals can and cannot be refunded on The Real Bingo.')

@section('content')
<div class="wrap doc">
    <h1>Refund Policy</h1>
    <p class="updated">Last updated: {{ date('d F Y') }}</p>

    <div class="notice" style="margin-bottom:2rem;">
        <strong>The most important point:</strong> money staked on a completed round cannot be refunded.
        A bet, once the round has settled, is final — win or lose. Please read this before depositing.
    </div>

    <h2>1. Deposits</h2>
    <p>Deposits are added to your wallet so you can play. Because funds become immediately available for use, deposits are generally not refundable once credited.</p>
    <p>We will consider a refund where:</p>
    <ul>
        <li>You were charged but the money was never credited to your wallet</li>
        <li>You were charged more than once for the same deposit</li>
        <li>A technical fault on our side caused an incorrect amount to be taken</li>
    </ul>
    <p>In these cases contact [SUPPORT EMAIL] with the transaction reference, amount and date.</p>

    <h2>2. Bets and gameplay</h2>
    <p>Once a bet is placed and the round has settled, the outcome is final. We cannot refund a losing bet, reverse a settled round, or reinstate a stake because of a change of mind, a misclick, or a lost connection after betting closed.</p>
    <p>Where a verified technical fault on our side prevented a round from settling correctly, we will correct the affected balances. This is a correction, not a refund of a valid losing bet.</p>

    <h2>3. Player-to-player transfers</h2>
    <p>Transfers between players are irreversible once completed. This is why the app shows you the recipient's name before you confirm. Always check it. We cannot recover funds sent to the wrong person.</p>

    <h2>4. Withdrawals</h2>
    <ul>
        <li>When you request a withdrawal, the amount is deducted from your balance immediately and held pending review.</li>
        <li>If the request is <strong>approved</strong>, the funds are sent to your verified bank or UPI details.</li>
        <li>If the request is <strong>rejected</strong> — for example because verification is incomplete or details do not match — the full amount is returned to your wallet automatically.</li>
        <li>A withdrawal already paid out cannot be reversed.</li>
    </ul>

    <h2>5. Bonuses</h2>
    <p>Referral and promotional bonuses are credited by us and hold no independent cash value. They are not refundable and are not paid out as cash where they were not earned in line with our terms. Bonuses obtained through fake accounts, self-referral or abuse will be removed.</p>

    <h2>6. Closed or suspended accounts</h2>
    <p>If your account is closed at your request, any remaining withdrawable balance will be paid to your verified details, subject to completed KYC. Where an account is suspended pending investigation of suspected fraud, collusion or breach of our terms, balances may be withheld until the investigation concludes.</p>

    <h2>7. How to request a refund</h2>
    <p>Email [SUPPORT EMAIL] with your registered mobile number, the transaction reference and date, the amount, and a description of the problem. We aim to acknowledge within [RESPONSE TIME] and to resolve within [RESOLUTION TIME]. Approved refunds are returned to the original payment method, and the time to appear depends on your bank or provider.</p>

    <h2>8. Disputes</h2>
    <p>If you are unhappy with a decision, reply to our response and ask for it to be escalated. Every bet, payout and transaction is permanently recorded, and we will review those records when investigating.</p>

    <h2>9. Contact</h2>
    <p>[SUPPORT EMAIL] &middot; [COMPANY LEGAL NAME], [REGISTERED ADDRESS]</p>
</div>
@endsection
