@extends('layouts.site')

@section('title', 'The Real Bingo — Live 60-Second Number Rounds')
@section('meta_description', 'Pick a number from 0 to 9, win 9x your bet. New round every 60 seconds, provably fair results you can verify yourself.')

@section('content')

<style>
    /* ── Hero ───────────────────────────────────────────────── */
    .hero { padding: 4.5rem 0 5rem; position: relative; overflow: hidden; }
    .hero-grid { display: grid; grid-template-columns: 1.1fr .9fr; gap: 3rem; align-items: center; }
    @media (max-width: 900px) { .hero-grid { grid-template-columns: 1fr; text-align: center; } }

    .pill {
        display: inline-flex; align-items: center; gap: .5rem;
        border: 1px solid var(--line); border-radius: 999px;
        padding: .34rem .9rem; font-size: .8rem; color: var(--gold);
        background: rgba(240,193,75,.06); margin-bottom: 1.3rem;
    }
    .dot { width: 7px; height: 7px; border-radius: 50%; background: #46d17f; }
    .pulse { animation: pulse 1.9s ease-in-out infinite; }
    @keyframes pulse { 0%,100% { opacity: 1; } 50% { opacity: .3; } }

    .hero h1 {
        font-size: clamp(2.3rem, 6.2vw, 4rem); font-weight: 900; letter-spacing: -.02em;
        background: linear-gradient(180deg, #fffdf5, #ffe9a8 35%, var(--gold) 65%, var(--gold-2));
        -webkit-background-clip: text; background-clip: text; color: transparent;
        margin-bottom: 1rem;
    }
    .hero p.sub { color: var(--muted); font-size: 1.08rem; max-width: 52ch; margin-bottom: 2rem; }
    @media (max-width: 900px) { .hero p.sub { margin-inline: auto; } }
    .cta-row { display: flex; gap: .9rem; flex-wrap: wrap; }
    @media (max-width: 900px) { .cta-row { justify-content: center; } }

    .stats { display: flex; gap: 2.4rem; margin-top: 2.6rem; flex-wrap: wrap; }
    @media (max-width: 900px) { .stats { justify-content: center; } }
    .stat-n {
        font-size: 1.9rem; font-weight: 800;
        background: linear-gradient(180deg, #fff3c4, var(--gold));
        -webkit-background-clip: text; background-clip: text; color: transparent;
    }
    .stat-l { font-size: .78rem; color: var(--muted); text-transform: uppercase; letter-spacing: .12em; }

    /* ── CSS roulette wheel (no image assets needed) ────────── */
    .wheel-stage { display: grid; place-items: center; position: relative; min-height: 340px; }
    .wheel {
        width: min(340px, 78vw); aspect-ratio: 1; border-radius: 50%; position: relative;
        background:
            conic-gradient(from 0deg,
                #8b1a1a 0deg 36deg, #1c1c1c 36deg 72deg, #8b1a1a 72deg 108deg,
                #1c1c1c 108deg 144deg, #8b1a1a 144deg 180deg, #1c1c1c 180deg 216deg,
                #8b1a1a 216deg 252deg, #1c1c1c 252deg 288deg, #8b1a1a 288deg 324deg,
                #1c1c1c 324deg 360deg);
        border: 10px solid transparent;
        background-origin: border-box;
        box-shadow: 0 0 0 9px #2a1608, 0 0 0 12px var(--gold-2),
                    0 22px 60px rgba(0,0,0,.6), inset 0 0 60px rgba(0,0,0,.55);
    }
    .spin { animation: spin 22s linear infinite; }
    @keyframes spin { to { transform: rotate(360deg); } }

    .wheel-hub {
        position: absolute; inset: 24%; border-radius: 50%;
        background: radial-gradient(circle at 35% 30%, #6d1f16, #3a0f0a 70%);
        border: 3px solid var(--gold-2);
        display: grid; place-items: center;
        box-shadow: inset 0 0 30px rgba(0,0,0,.7);
    }
    .wheel-num {
        font-size: 2.6rem; font-weight: 900; color: var(--gold);
        text-shadow: 0 0 22px rgba(240,193,75,.65);
        font-variant-numeric: tabular-nums;
    }
    .wheel-pointer {
        position: absolute; top: -6px; left: 50%; transform: translateX(-50%);
        width: 0; height: 0; z-index: 3;
        border-left: 13px solid transparent; border-right: 13px solid transparent;
        border-top: 24px solid var(--gold);
        filter: drop-shadow(0 3px 6px rgba(0,0,0,.7));
    }

    .chip {
        position: absolute; width: 62px; height: 62px; border-radius: 50%;
        display: grid; place-items: center; font-weight: 800; font-size: .95rem; color: #fff;
        border: 4px dashed rgba(255,255,255,.75);
        box-shadow: 0 10px 26px rgba(0,0,0,.5);
    }
    .chip-1 { background: #c0392b; top: 4%;  left: 2%;  animation: float 5.5s ease-in-out infinite; }
    .chip-2 { background: #2471a3; bottom: 8%; left: 8%; animation: float 6.8s ease-in-out infinite .8s; }
    .chip-3 { background: #1e8449; top: 12%; right: 3%; animation: float 6.1s ease-in-out infinite .4s; }
    @keyframes float { 0%,100% { transform: translateY(0) rotate(-6deg); } 50% { transform: translateY(-16px) rotate(6deg); } }
    @media (max-width: 900px) { .chip { display: none; } }

    /* ── Feature grid ──────────────────────────────────────── */
    .grid-3 { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 1.3rem; }
    .feat-ico {
        width: 46px; height: 46px; border-radius: 12px; display: grid; place-items: center;
        background: linear-gradient(145deg, rgba(240,193,75,.2), rgba(240,193,75,.05));
        border: 1px solid var(--line); font-size: 1.35rem; margin-bottom: .9rem;
    }
    .card h3 { font-size: 1.06rem; margin-bottom: .4rem; color: var(--gold); }
    .card p { color: var(--muted); font-size: .94rem; }

    /* ── Steps ─────────────────────────────────────────────── */
    .steps { display: grid; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); gap: 1.3rem; counter-reset: s; }
    .step { position: relative; padding-top: 2.6rem; }
    .step::before {
        counter-increment: s; content: counter(s);
        position: absolute; top: 0; left: 0;
        width: 42px; height: 42px; border-radius: 50%; display: grid; place-items: center;
        font-weight: 800; color: #3d2703;
        background: linear-gradient(180deg, #ffe9a8, var(--gold) 45%, var(--gold-2));
        box-shadow: 0 5px 16px rgba(240,193,75,.3);
    }
    .step h3 { color: var(--gold); font-size: 1.04rem; margin-bottom: .35rem; }
    .step p { color: var(--muted); font-size: .94rem; }

    /* ── Number strip ──────────────────────────────────────── */
    .numbers { display: flex; gap: .55rem; flex-wrap: wrap; justify-content: center; margin-top: 2rem; }
    .num {
        width: 52px; height: 52px; border-radius: 12px; display: grid; place-items: center;
        font-weight: 800; font-size: 1.15rem; border: 1px solid var(--line);
        background: linear-gradient(180deg, rgba(139,26,26,.55), rgba(28,28,28,.55));
    }
    .num:nth-child(odd) { background: linear-gradient(180deg, rgba(28,28,28,.6), rgba(60,60,60,.35)); }

    /* ── FAQ accordion ─────────────────────────────────────── */
    .faq { max-width: 800px; margin-inline: auto; }
    details.faq-item {
        border: 1px solid var(--line); border-radius: 12px;
        background: var(--panel); margin-bottom: .75rem; overflow: hidden;
    }
    details.faq-item summary {
        cursor: pointer; list-style: none; padding: 1.05rem 1.3rem;
        font-weight: 600; display: flex; justify-content: space-between; gap: 1rem; align-items: center;
    }
    details.faq-item summary::-webkit-details-marker { display: none; }
    details.faq-item summary::after {
        content: '+'; color: var(--gold); font-size: 1.5rem; line-height: 1; transition: transform .25s;
    }
    details.faq-item[open] summary::after { transform: rotate(45deg); }
    details.faq-item .faq-body { padding: 0 1.3rem 1.15rem; color: var(--muted); font-size: .95rem; }

    /* ── Download ──────────────────────────────────────────── */
    .dl {
        background: linear-gradient(145deg, rgba(240,193,75,.11), rgba(139,26,26,.16));
        border: 1px solid var(--line); border-radius: 22px; padding: 3rem 2rem; text-align: center;
    }
    .dl-meta { color: var(--muted); font-size: .88rem; margin-top: 1rem; }
    .steps-install { max-width: 560px; margin: 2rem auto 0; text-align: left; }
    .steps-install li { color: var(--muted); font-size: .92rem; margin-bottom: .45rem; }
</style>

{{-- ══════════════ HERO ══════════════ --}}
<div class="hero">
    <div class="wrap hero-grid">
        <div>
            <span class="pill"><span class="dot pulse"></span> A new round every 60 seconds</span>
            <h1>Pick a number.<br>Win 9&times; your bet.</h1>
            <p class="sub">
                Ten numbers. Sixty seconds. One winner. Every result is locked in
                before betting opens and can be verified by you afterwards — so the
                outcome is never decided by how anyone bets.
            </p>
            <div class="cta-row">
                <a class="btn" href="#download">Download for Android</a>
                <a class="btn btn-ghost" href="#how">How it works</a>
            </div>

            <div class="stats">
                <div>
                    <div class="stat-n">9&times;</div>
                    <div class="stat-l">Payout</div>
                </div>
                <div>
                    <div class="stat-n">60s</div>
                    <div class="stat-l">Per round</div>
                </div>
                <div>
                    <div class="stat-n">0&ndash;9</div>
                    <div class="stat-l">Numbers</div>
                </div>
                <div>
                    <div class="stat-n">24/7</div>
                    <div class="stat-l">Always live</div>
                </div>
            </div>
        </div>

        <div class="wheel-stage">
            <div class="chip chip-1">100</div>
            <div class="chip chip-2">500</div>
            <div class="chip chip-3">1K</div>
            <div class="wheel-pointer"></div>
            <div class="wheel spin"></div>
            <div class="wheel-hub"><span class="wheel-num" id="demoNum">7</span></div>
        </div>
    </div>
</div>

{{-- ══════════════ FEATURES ══════════════ --}}
<section>
    <div class="wrap">
        <div class="center reveal">
            <h2>Built to be trusted</h2>
            <p class="lead">Every part of the game is designed so you never have to take our word for it.</p>
        </div>

        <div class="grid-3">
            <div class="card reveal">
                <div class="feat-ico">🔒</div>
                <h3>Provably fair</h3>
                <p>The winning number is locked in with a secret code before betting opens. We publish its fingerprint up front and reveal the code afterwards, so you can check it was never changed.</p>
            </div>
            <div class="card reveal">
                <div class="feat-ico">⚡</div>
                <h3>Rounds every minute</h3>
                <p>55 seconds to place your bets, then a 5-second spin. The next round starts immediately — no waiting, no dead time between games.</p>
            </div>
            <div class="card reveal">
                <div class="feat-ico">🕐</div>
                <h3>Perfectly in sync</h3>
                <p>Rounds run on the server clock, so every player everywhere sees the exact same countdown and the exact same result at the same moment.</p>
            </div>
            <div class="card reveal">
                <div class="feat-ico">💰</div>
                <h3>Exact money handling</h3>
                <p>Every rupee movement is written to a permanent ledger with a running balance. Nothing is rounded, nothing is estimated, nothing goes missing.</p>
            </div>
            <div class="card reveal">
                <div class="feat-ico">🎁</div>
                <h3>Refer &amp; earn</h3>
                <p>Share your referral code with friends. When someone you invited makes their first deposit, your bonus is credited automatically.</p>
            </div>
            <div class="card reveal">
                <div class="feat-ico">🛡️</div>
                <h3>Verified withdrawals</h3>
                <p>KYC verification and your own bank or UPI details are required before any payout, keeping your winnings tied to you and nobody else.</p>
            </div>
        </div>
    </div>
</section>

{{-- ══════════════ HOW TO PLAY ══════════════ --}}
<section id="how">
    <div class="wrap">
        <div class="center reveal">
            <h2>How to play</h2>
            <p class="lead">If you can pick a number between 0 and 9, you already know how.</p>
        </div>

        <div class="steps reveal">
            <div class="step">
                <h3>Add money</h3>
                <p>Top up your wallet from the Add Cash screen. Your balance is always shown at the top of the game.</p>
            </div>
            <div class="step">
                <h3>Pick your numbers</h3>
                <p>Tap any number from 0 to 9, then tap a chip to stake that amount on it. You can back several numbers in the same round.</p>
            </div>
            <div class="step">
                <h3>Watch the spin</h3>
                <p>When the 55-second betting window closes, the wheel spins for 5 seconds and lands on the winning number.</p>
            </div>
            <div class="step">
                <h3>Get paid</h3>
                <p>Back the winning number and you're paid 9&times; your stake on it, credited to your wallet straight away.</p>
            </div>
        </div>

        <div class="numbers reveal">
            @foreach (range(0, 9) as $n)
                <div class="num">{{ $n }}</div>
            @endforeach
        </div>

        <div class="center" style="margin-top:2.2rem;">
            <p style="color:var(--muted);font-size:.93rem;">Minimum bet is Rs. 10 per number.</p>
        </div>
    </div>
</section>

{{-- ══════════════ FAIR PLAY ══════════════ --}}
<section id="fair">
    <div class="wrap">
        <div class="center reveal">
            <h2>Why you can trust the result</h2>
            <p class="lead">
                "Provably fair" is not a slogan here — it is something you can actually check yourself, on every single round.
            </p>
        </div>

        <div class="grid-3 reveal">
            <div class="card">
                <h3>1. Before betting opens</h3>
                <p>The server generates a secret code and decides the winning number from it. You are shown a fingerprint (a SHA-256 hash) of that code immediately — proof the number already exists.</p>
            </div>
            <div class="card">
                <h3>2. While betting is open</h3>
                <p>Bets cannot influence the outcome. The number was fixed before the first bet was placed and nothing about how players bet can change it.</p>
            </div>
            <div class="card">
                <h3>3. After the round settles</h3>
                <p>The secret code is revealed. Hash it yourself and compare it to the fingerprint published earlier — if they match, the result was never tampered with.</p>
            </div>
        </div>

        <div class="notice reveal" style="margin-top:1.8rem;">
            The house edge comes from the payout maths — 9&times; on a 1-in-10 chance — and never from adjusting results. That is the whole point of publishing the fingerprint up front.
        </div>
    </div>
</section>

{{-- ══════════════ FAQ ══════════════ --}}
<section id="faq">
    <div class="wrap">
        <div class="center reveal">
            <h2>Frequently asked questions</h2>
            <p class="lead">Everything players ask most often, answered plainly.</p>
        </div>

        <div class="faq reveal">
            @php
                $faqs = [
                    ['Is the game fair?', 'Yes, and you can prove it yourself. Before each round opens we publish a fingerprint of the secret code that determines the winning number. After the round we reveal the code. If the fingerprint matches, the result was fixed before anyone bet and could not have been changed.'],
                    ['What is the minimum bet?', 'Rs. 10 per number. You can back as many different numbers as you like in the same round.'],
                    ['How much do I win?', 'A correct number pays 9 times whatever you staked on it, credited to your wallet as soon as the round settles.'],
                    ['How long does a round last?', 'Exactly 60 seconds — 55 seconds of betting followed by a 5-second spin. The next round begins immediately after.'],
                    ['How do I withdraw my winnings?', 'Complete KYC verification, add your bank or UPI details, then request a withdrawal from your wallet. The amount is held immediately and paid out once an administrator approves it.'],
                    ['Why do I need to complete KYC?', 'Identity verification is required before any withdrawal. It protects your account, keeps winnings tied to the right person, and is a standard requirement for real-money platforms.'],
                    ['How long do withdrawals take?', 'Requests are reviewed by an administrator before payout. Timing depends on the review and your bank; you can track the status of every request in the app.'],
                    ['How does the referral bonus work?', 'Share your referral code from the Refer & Earn screen. When someone who signed up with your code makes their first successful deposit, your bonus is credited automatically. You can see who has joined and who has earned you a bonus under My Referrals.'],
                    ['Can I send money to a friend?', 'Yes. You can transfer to another player using their mobile number, email or username. You will always be shown who you are about to pay before the transfer goes through, because transfers cannot be reversed.'],
                    ['Why is the app not on the Play Store?', 'Real-money gaming apps have restricted distribution on app stores, so the app is downloaded directly from this site. You will need to allow installation from unknown sources once, which Android will prompt you for.'],
                    ['Is there an age limit?', 'Yes. You must be at least 18 years old to create an account and play.'],
                ];
            @endphp

            @foreach ($faqs as $i => $faq)
                <details class="faq-item" @if($i === 0) open @endif>
                    <summary>{{ $faq[0] }}</summary>
                    <div class="faq-body">{{ $faq[1] }}</div>
                </details>
            @endforeach
        </div>
    </div>
</section>

{{-- ══════════════ DOWNLOAD ══════════════ --}}
<section id="download">
    <div class="wrap">
        <div class="dl reveal">
            <h2>Get the app</h2>
            <p class="lead center" style="margin-bottom:1.8rem;">
                Available for Android. Download the latest build directly below.
            </p>

            @if ($version && $version->resolvedDownloadUrl())
                <a class="btn" style="font-size:1.05rem;padding:.95rem 2.2rem;"
                   href="{{ $version->resolvedDownloadUrl() }}">
                    Download APK &middot; v{{ $version->version_name }}
                </a>
                <div class="dl-meta">
                    Version {{ $version->version_name }} (build {{ $version->version_code }})
                    &middot; Updated {{ $version->updated_at->format('d M Y') }}
                </div>
                @if ($version->changelog)
                    <div class="notice" style="max-width:560px;margin:1.6rem auto 0;text-align:left;">
                        <strong>What's new</strong><br>
                        {!! nl2br(e($version->changelog)) !!}
                    </div>
                @endif
            @else
                <div class="notice" style="max-width:560px;margin-inline:auto;">
                    The download will appear here as soon as the first build is published.
                </div>
            @endif

            <ol class="steps-install">
                <li>Tap the download button above to get the APK file.</li>
                <li>Open it. Android will ask permission to install from this source — allow it.</li>
                <li>Install, open the app, and register with your mobile number.</li>
                <li>Complete your profile and KYC so withdrawals are ready when you need them.</li>
            </ol>

            <p class="dl-meta" style="margin-top:1.6rem;">
                18+ only. Please play responsibly.
            </p>
        </div>
    </div>
</section>

@push('scripts')
<script>
    // Cosmetic only: cycles the number in the wheel hub so the hero feels alive.
    (function () {
        var el = document.getElementById('demoNum');
        if (!el || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
        setInterval(function () {
            el.textContent = Math.floor(Math.random() * 10);
        }, 2200);
    })();
</script>
@endpush

@endsection
