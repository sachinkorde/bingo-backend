{{-- Server clock + current session, shown in the dashboard top bar for every
     operator. Syncs the server time ONCE then counts locally — no per-second
     server calls (same logic the game client uses). --}}
<div id="rb-server-timer"
     style="display:flex;align-items:center;gap:.4rem;font-weight:600;font-size:.8rem;padding:0 .75rem;white-space:nowrap;">
    <span style="opacity:.6">Session</span>
    <span id="rb-slot">—</span>
    <span style="opacity:.4">·</span>
    <span id="rb-clock">--:--:--</span>
    <span style="opacity:.4">·</span>
    <span id="rb-left" style="min-width:2.6rem;text-align:right;color:#f59e0b;">--s</span>
</div>
<script>
(function () {
    var offset = 0, sessionSeconds = 60, bettingSeconds = 40, synced = false;

    function syncOnce() {
        fetch('{{ url('/api/server-time') }}', { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res || !res.data) return;
                offset = res.data.server_time - Date.now();          // sync once
                sessionSeconds = res.data.session_seconds || 60;
                bettingSeconds = res.data.betting_seconds || 40;
                synced = true;
            })
            .catch(function () {});
    }

    function pad(n) { return String(n).padStart(2, '0'); }

    function tick() {
        if (!synced) return;
        var now = Date.now() + offset;                                // local clock + offset
        var sec = Math.floor(now / 1000);
        var slot = Math.floor(sec / sessionSeconds);
        var into = sec % sessionSeconds;
        // Same value the GAME shows: betting countdown (betting→0), then 0 during spin/result.
        var left = (into < bettingSeconds) ? (bettingSeconds - into) : 0;
        var d = new Date(now);

        var slotEl = document.getElementById('rb-slot');
        var clockEl = document.getElementById('rb-clock');
        var leftEl = document.getElementById('rb-left');
        if (slotEl) slotEl.textContent = '#' + slot;
        if (clockEl) clockEl.textContent = pad(d.getHours()) + ':' + pad(d.getMinutes()) + ':' + pad(d.getSeconds());
        if (leftEl) leftEl.textContent = left + 's';
    }

    syncOnce();
    setInterval(tick, 250);
    setInterval(syncOnce, 300000); // gentle re-sync every 5 min (NOT every second)
})();
</script>
