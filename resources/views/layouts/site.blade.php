<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'The Real Bingo')</title>
    <meta name="description" content="@yield('meta_description', 'The Real Bingo — live 60-second number rounds with provably fair results.')">
    <style>
        /* Self-contained: no external fonts/CDNs, so the page renders
           identically offline and on a slow connection. */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --bg: #16060a;
            --bg-2: #24090f;
            --panel: rgba(255, 255, 255, .04);
            --gold: #f0c14b;
            --gold-2: #b8860b;
            --red: #8b1a1a;
            --text: #f4ece4;
            --muted: #b9a99c;
            --line: rgba(240, 193, 75, .22);
        }

        html { scroll-behavior: smooth; }

        body {
            background: radial-gradient(circle at 50% 0%, #3a0d16 0%, var(--bg) 55%, #0d0407 100%);
            color: var(--text);
            font-family: "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            line-height: 1.65;
            overflow-x: hidden;
        }

        .wrap { width: min(1120px, 92vw); margin-inline: auto; }

        /* ── Header ─────────────────────────────────────────────── */
        header {
            position: sticky; top: 0; z-index: 50;
            backdrop-filter: blur(12px);
            background: rgba(13, 4, 7, .82);
            border-bottom: 1px solid var(--line);
        }
        .nav { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: .85rem 0; }
        .brand { display: flex; align-items: baseline; gap: .5rem; text-decoration: none; }
        .brand-sm { font-size: .62rem; letter-spacing: .42em; color: var(--muted); text-transform: uppercase; }
        .brand-lg {
            font-size: 1.5rem; font-weight: 800; letter-spacing: .04em;
            background: linear-gradient(180deg, #fff3c4, var(--gold) 45%, var(--gold-2));
            -webkit-background-clip: text; background-clip: text; color: transparent;
        }
        .nav-links { display: flex; gap: 1.4rem; align-items: center; flex-wrap: wrap; }
        .nav-links a { color: var(--muted); text-decoration: none; font-size: .92rem; transition: color .2s; }
        .nav-links a:hover { color: var(--gold); }
        @media (max-width: 860px) { .nav-links { display: none; } }

        .btn {
            display: inline-block; text-decoration: none; cursor: pointer;
            padding: .78rem 1.6rem; border-radius: 999px; border: 0;
            font-weight: 700; font-size: .95rem;
            background: linear-gradient(180deg, #ffe9a8, var(--gold) 40%, var(--gold-2));
            color: #3d2703;
            box-shadow: 0 6px 22px rgba(240, 193, 75, .28);
            transition: transform .18s, box-shadow .18s;
        }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 10px 30px rgba(240, 193, 75, .42); }
        .btn-ghost {
            background: transparent; color: var(--gold);
            border: 1px solid var(--line); box-shadow: none;
        }

        /* ── Shared section styling ─────────────────────────────── */
        section { padding: 5rem 0; }
        h1, h2, h3 { line-height: 1.2; }
        h2 {
            font-size: clamp(1.7rem, 4vw, 2.5rem); margin-bottom: .7rem; font-weight: 800;
            background: linear-gradient(180deg, #fff6d8, var(--gold) 60%, var(--gold-2));
            -webkit-background-clip: text; background-clip: text; color: transparent;
        }
        .lead { color: var(--muted); max-width: 62ch; margin-bottom: 2.6rem; }
        .center { text-align: center; }
        .center .lead { margin-inline: auto; }

        .card {
            background: var(--panel); border: 1px solid var(--line);
            border-radius: 16px; padding: 1.6rem;
        }

        /* ── Scroll reveal (opt-in, disabled if JS is off) ──────── */
        .reveal { opacity: 1; transform: none; }
        .js .reveal { opacity: 0; transform: translateY(22px); transition: opacity .6s ease, transform .6s ease; }
        .js .reveal.in { opacity: 1; transform: none; }

        @media (prefers-reduced-motion: reduce) {
            html { scroll-behavior: auto; }
            .js .reveal { opacity: 1; transform: none; transition: none; }
            .spin, .float, .pulse { animation: none !important; }
        }

        footer {
            border-top: 1px solid var(--line); padding: 3rem 0 2.4rem;
            color: var(--muted); font-size: .9rem;
        }
        .foot-grid { display: flex; flex-wrap: wrap; gap: 2rem; justify-content: space-between; }
        .foot-links { display: flex; flex-direction: column; gap: .5rem; }
        .foot-links a { color: var(--muted); text-decoration: none; }
        .foot-links a:hover { color: var(--gold); }
        .foot-bottom { margin-top: 2.2rem; padding-top: 1.4rem; border-top: 1px solid rgba(255,255,255,.06); font-size: .84rem; }

        .notice {
            border: 1px solid rgba(240,193,75,.3); background: rgba(240,193,75,.07);
            border-radius: 12px; padding: 1rem 1.15rem; color: #e8d9b4; font-size: .9rem;
        }

        /* ── Legal / long-form document pages ───────────────────── */
        .doc { padding: 3.5rem 0 5rem; }
        .doc h1 { font-size: clamp(1.9rem, 5vw, 2.7rem); margin-bottom: .4rem;
            background: linear-gradient(180deg, #fff6d8, var(--gold) 60%, var(--gold-2));
            -webkit-background-clip: text; background-clip: text; color: transparent; }
        .doc .updated { color: var(--muted); font-size: .88rem; margin-bottom: 2.4rem; }
        .doc h2 { font-size: 1.22rem; margin: 2.2rem 0 .6rem; }
        .doc p, .doc li { color: #ded2c6; }
        .doc ul, .doc ol { margin: .6rem 0 .6rem 1.3rem; }
        .doc li { margin-bottom: .4rem; }
        .doc a { color: var(--gold); }
    </style>
</head>
<body>
<script>document.body.parentElement.classList.add('js');</script>

<header>
    <div class="wrap nav">
        <a class="brand" href="{{ url('/') }}">
            <span class="brand-sm">The Real</span>
            <span class="brand-lg">BINGO</span>
        </a>
        <nav class="nav-links">
            <a href="{{ url('/#how') }}">How to Play</a>
            <a href="{{ url('/#fair') }}">Fair Play</a>
            <a href="{{ url('/#faq') }}">FAQ</a>
            <a href="{{ route('terms') }}">Terms</a>
            <a href="{{ route('privacy') }}">Privacy</a>
            <a href="{{ route('refund') }}">Refunds</a>
        </nav>
        <a class="btn" href="{{ url('/#download') }}">Download</a>
    </div>
</header>

@yield('content')

<footer>
    <div class="wrap">
        <div class="foot-grid">
            <div style="max-width: 34ch;">
                <a class="brand" href="{{ url('/') }}">
                    <span class="brand-sm">The Real</span>
                    <span class="brand-lg">BINGO</span>
                </a>
                <p style="margin-top:.7rem;">Live 60-second number rounds with provably fair, independently verifiable results.</p>
            </div>
            <div class="foot-links">
                <strong style="color:var(--text);">Game</strong>
                <a href="{{ url('/#how') }}">How to Play</a>
                <a href="{{ url('/#fair') }}">Fair Play</a>
                <a href="{{ url('/#faq') }}">FAQ</a>
            </div>
            <div class="foot-links">
                <strong style="color:var(--text);">Legal</strong>
                <a href="{{ route('terms') }}">Terms &amp; Conditions</a>
                <a href="{{ route('privacy') }}">Privacy Policy</a>
                <a href="{{ route('refund') }}">Refund Policy</a>
            </div>
        </div>

        <div class="foot-bottom">
            <p><strong style="color:#e8d9b4;">18+ only.</strong> This game involves real money and carries financial risk. Play responsibly and only with money you can afford to lose. Availability is restricted in some regions — see the Terms &amp; Conditions.</p>
            <p style="margin-top:.8rem;">&copy; {{ date('Y') }} The Real Bingo. All rights reserved.</p>
        </div>
    </div>
</footer>

<script>
    // Scroll reveal — progressive enhancement only. Without JS the .reveal
    // elements stay fully visible (see the .js prefix in the CSS above).
    (function () {
        var els = document.querySelectorAll('.reveal');
        if (!('IntersectionObserver' in window)) {
            els.forEach(function (el) { el.classList.add('in'); });
            return;
        }
        var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (e) {
                if (e.isIntersecting) { e.target.classList.add('in'); io.unobserve(e.target); }
            });
        }, { threshold: .12 });
        els.forEach(function (el) { io.observe(el); });
    })();
</script>
@stack('scripts')
</body>
</html>
