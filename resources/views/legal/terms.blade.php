{{--
    ⚠ DRAFT — REQUIRES LEGAL REVIEW BEFORE LAUNCH.
    Real-money gaming is regulated differently in each Indian state and several
    prohibit it outright. Placeholders in [SQUARE BRACKETS] must be completed,
    and a qualified lawyer must review the whole document, before this is
    relied on as a binding agreement.
--}}
@extends('layouts.site')

@section('title', 'Terms & Conditions — The Real Bingo')
@section('meta_description', 'The terms governing your use of The Real Bingo.')

@section('content')
<div class="wrap doc">
    <h1>Terms &amp; Conditions</h1>
    <p class="updated">Last updated: {{ date('d F Y') }}</p>

    <div class="notice" style="margin-bottom:2rem;">
        Please read these terms carefully. By creating an account or using The Real Bingo,
        you agree to be bound by them. If you do not agree, do not use the service.
    </div>

    <h2>1. Who we are</h2>
    <p>The Real Bingo ("the platform", "we", "us") is operated by [COMPANY LEGAL NAME], [REGISTERED ADDRESS], [COMPANY REGISTRATION NUMBER]. You can contact us at [SUPPORT EMAIL].</p>

    <h2>2. Eligibility</h2>
    <ul>
        <li>You must be at least 18 years old.</li>
        <li>You must be legally permitted to take part in real-money gaming in your jurisdiction. Several Indian states restrict or prohibit real-money gaming, and it is your responsibility to know the law where you are.</li>
        <li>You must register in your own name, using your own details and your own payment methods.</li>
        <li>One account per person. Multiple accounts held by the same individual may be closed and balances withheld pending investigation.</li>
    </ul>

    <h2>3. Your account</h2>
    <ul>
        <li>You are responsible for keeping your login details secure. Activity carried out through your account is treated as yours.</li>
        <li>The information you provide must be accurate and kept up to date. Inaccurate details may delay or prevent withdrawals.</li>
        <li>We may suspend or close accounts where we reasonably suspect fraud, collusion, multiple accounts, money laundering, or breach of these terms.</li>
        <li>Tell us immediately at [SUPPORT EMAIL] if you believe your account has been accessed by someone else.</li>
    </ul>

    <h2>4. Identity verification (KYC)</h2>
    <p>Identity verification is required before any withdrawal. We may ask for government-issued identification, proof of address, and confirmation of the payment method used. We may also request verification at other times where we are required to, or where we reasonably suspect a breach of these terms. Withdrawals remain on hold until verification is complete.</p>

    <h2>5. How the game works</h2>
    <ul>
        <li>Each round lasts 60 seconds: 55 seconds of betting followed by a 5-second spin.</li>
        <li>Players bet on numbers 0 to 9. A correct number pays 9&times; the amount staked on it.</li>
        <li>The minimum bet is Rs. 10 per number. Maximum stake and payout limits apply and may be changed.</li>
        <li>The winning number is generated from a secret code created before betting opens. A cryptographic fingerprint of that code is published in advance and the code is revealed after settlement, so results can be independently verified.</li>
        <li>Bets are final once submitted. Rounds cannot be cancelled or re-run once betting has closed.</li>
    </ul>

    <h2>6. Wallet, deposits and withdrawals</h2>
    <ul>
        <li>Funds in your wallet are held for the purpose of play and withdrawal. They do not earn interest.</li>
        <li>Deposits are credited once the payment has been confirmed. Confirmation timing depends on the payment provider.</li>
        <li>Withdrawal requests require completed KYC and valid bank or UPI details in your own name. The amount is deducted from your balance at the point of request and paid out once approved.</li>
        <li>We may decline a withdrawal where verification is incomplete, where details do not match the account holder, or where we are investigating a suspected breach of these terms.</li>
        <li>Player-to-player transfers are subject to limits and are not reversible once completed.</li>
    </ul>

    <h2>7. Bonuses and referrals</h2>
    <ul>
        <li>A referral bonus is credited to the inviting player when a player who signed up with their referral code makes their first successful deposit.</li>
        <li>Bonus amounts are set by us and may change at any time. The amount that applies is the one in effect when the qualifying deposit is made.</li>
        <li>Bonuses obtained through fake accounts, self-referral, or any attempt to game the system will be removed and may result in account closure.</li>
    </ul>

    <h2>8. Fair play</h2>
    <p>The following are prohibited and may result in immediate account closure and forfeiture of balances: use of bots, scripts or automation; exploiting bugs or errors rather than reporting them; collusion between accounts; using another person's identity or payment method; and any attempt to interfere with the platform's operation or security.</p>

    <h2>9. Errors</h2>
    <p>Where a technical fault, human error, or malfunction results in an incorrect payout, balance or result, we reserve the right to correct it, including by adjusting balances. We will act reasonably and will tell you when we do this.</p>

    <h2>10. Responsible play</h2>
    <p>This game involves real money and financial risk. It is entertainment, not a source of income. Only play with money you can afford to lose. If play is affecting your finances, work, or relationships, stop and seek support. Contact us at [SUPPORT EMAIL] if you would like your account closed.</p>

    <h2>11. Service availability</h2>
    <p>We aim to keep the platform available continuously but do not guarantee uninterrupted service. Maintenance, technical faults, or circumstances beyond our control may interrupt access. We are not liable for losses arising from interruptions, connectivity problems on your side, or device faults.</p>

    <h2>12. Limitation of liability</h2>
    <p>To the fullest extent permitted by law, our liability to you in connection with the platform is limited to the balance held in your wallet at the relevant time. We are not liable for indirect or consequential losses, including loss of profit or opportunity.</p>

    <h2>13. Changes to these terms</h2>
    <p>We may update these terms. Material changes will be notified in the app or on this page, and the "last updated" date above will change. Continuing to use the platform after a change means you accept the updated terms.</p>

    <h2>14. Governing law</h2>
    <p>These terms are governed by the laws of India, and the courts of [CITY / JURISDICTION] have exclusive jurisdiction over any dispute arising from them.</p>

    <h2>15. Contact</h2>
    <p>Questions about these terms: [SUPPORT EMAIL].</p>
</div>
@endsection
