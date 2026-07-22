using System;
using System.Collections;
using UnityEngine;

/// <summary>
/// Local mirror of the server's game clock.
///
/// The backend derives everything from the wall clock:
///     slot_no = floor(unixSeconds / session_seconds)
/// so once we know the server's time we can compute the current round and the
/// exact seconds remaining WITHOUT polling. Every device that has synced shows
/// the same second.
///
/// Do NOT call /round/current every second — that defeats the entire design and
/// will hammer your free-tier instance. Sync on start, then roughly once a
/// minute to correct drift.
///
/// Attach to a persistent GameObject in your first scene.
/// </summary>
public class BingoClock : MonoBehaviour
{
    public static BingoClock Instance { get; private set; }

    [Header("Filled in from the server on first sync")]
    public int SessionSeconds = 60;
    public int BettingSeconds = 55;
    public int SpinSeconds = 5;
    public int Numbers = 10;
    public int PayoutMultiplier = 9;

    [Header("How often to correct clock drift (seconds)")]
    public float ResyncInterval = 60f;

    /// <summary>Fires when a new round begins. Argument is the new slot_no.</summary>
    public event Action<long> OnRoundStarted;

    /// <summary>Fires the moment betting closes and the spin begins.</summary>
    public event Action<long> OnBettingClosed;

    public bool IsSynced { get; private set; }

    private double _offsetMs;          // serverNow - localNow
    private long _lastSlotNo = -1;
    private bool _bettingClosedFired;

    private void Awake()
    {
        if (Instance != null && Instance != this) { Destroy(gameObject); return; }
        Instance = this;
        DontDestroyOnLoad(gameObject);
    }

    private void Start() => StartCoroutine(SyncLoop());

    private IEnumerator SyncLoop()
    {
        while (true)
        {
            yield return Sync();
            yield return new WaitForSeconds(IsSynced ? ResyncInterval : 5f);
        }
    }

    public IEnumerator Sync()
    {
        var sentAt = LocalNowMs();

        yield return BingoApi.GetServerTime(data =>
        {
            // Half the round trip has elapsed since the server stamped the time.
            var rtt = LocalNowMs() - sentAt;
            _offsetMs = data.server_time + (rtt / 2.0) - LocalNowMs();

            SessionSeconds = Mathf.Max(1, data.session_seconds);
            BettingSeconds = data.betting_seconds;

            IsSynced = true;
        },
        err => Debug.LogWarning("[BingoClock] sync failed: " + err));
    }

    /// <summary>Also picks up numbers/multiplier/spin, which /server-time omits.</summary>
    public IEnumerator SyncFull()
    {
        var sentAt = LocalNowMs();

        yield return BingoApi.GetCurrentRound(data =>
        {
            var rtt = LocalNowMs() - sentAt;
            _offsetMs = data.server_time + (rtt / 2.0) - LocalNowMs();

            SessionSeconds = Mathf.Max(1, data.session_seconds);
            BettingSeconds = data.betting_seconds;
            SpinSeconds = data.spin_seconds;
            Numbers = data.numbers;
            PayoutMultiplier = data.payout_multiplier;

            IsSynced = true;
        },
        err => Debug.LogWarning("[BingoClock] full sync failed: " + err));
    }

    private void Update()
    {
        if (!IsSynced) return;

        var slot = SlotNo;

        if (slot != _lastSlotNo)
        {
            _lastSlotNo = slot;
            _bettingClosedFired = false;
            OnRoundStarted?.Invoke(slot);
        }

        if (!_bettingClosedFired && !IsBetting)
        {
            _bettingClosedFired = true;
            OnBettingClosed?.Invoke(slot);
        }
    }

    // ── Derived state (all local, zero network) ─────────────────────────────

    public long ServerNowMs => LocalNowMs() + (long)_offsetMs;

    private long ServerNowSeconds => ServerNowMs / 1000L;

    /// <summary>Matches the server's intdiv(now, session_seconds) exactly.</summary>
    public long SlotNo => ServerNowSeconds / SessionSeconds;

    public int SecondsIntoSlot => (int)(ServerNowSeconds % SessionSeconds);

    public bool IsBetting => SecondsIntoSlot < BettingSeconds;

    /// <summary>Seconds until betting closes. 0 once the spin has started.</summary>
    public int BettingSecondsLeft => Mathf.Max(0, BettingSeconds - SecondsIntoSlot);

    /// <summary>Seconds until the whole round ends (spin included).</summary>
    public int SessionSecondsLeft => Mathf.Max(0, SessionSeconds - SecondsIntoSlot);

    private static long LocalNowMs() => DateTimeOffset.UtcNow.ToUnixTimeMilliseconds();
}
