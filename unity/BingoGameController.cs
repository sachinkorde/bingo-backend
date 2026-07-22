using System.Collections;
using System.Collections.Generic;
using UnityEngine;
using UnityEngine.UI;

/// <summary>
/// Reference implementation of one full game loop. Wire your own UI to it, or
/// copy the pattern — the important parts are the ORDER of operations, not the
/// specific widgets.
/// </summary>
public class BingoGameController : MonoBehaviour
{
    [Header("UI")]
    public Text timerText;
    public Text balanceText;
    public Text statusText;
    public Text winningNumberText;
    public Button confirmBetButton;

    [Header("Bet")]
    public float chipAmount = 10f;   // must be >= GAME_MIN_BET on the server

    private readonly Dictionary<int, float> _pendingBids = new();
    private int _lastSlotId = -1;
    private bool _betPlaced;

    private void Start()
    {
        if (!BingoApi.IsLoggedIn)
        {
            statusText.text = "Please log in.";
            return;
        }

        BingoClock.Instance.OnRoundStarted += HandleRoundStarted;
        BingoClock.Instance.OnBettingClosed += HandleBettingClosed;

        // Full sync once so we get numbers / multiplier / spin_seconds too.
        StartCoroutine(BingoClock.Instance.SyncFull());
        StartCoroutine(RefreshBalance());
    }

    private void OnDestroy()
    {
        if (BingoClock.Instance == null) return;
        BingoClock.Instance.OnRoundStarted -= HandleRoundStarted;
        BingoClock.Instance.OnBettingClosed -= HandleBettingClosed;
    }

    private void Update()
    {
        var clock = BingoClock.Instance;
        if (clock == null || !clock.IsSynced)
        {
            // Free-tier cold start can take ~50s. Say so instead of looking frozen.
            statusText.text = "Connecting…";
            return;
        }

        if (clock.IsBetting)
        {
            timerText.text = clock.BettingSecondsLeft.ToString();
            statusText.text = _betPlaced ? "Bet placed" : "Place your bet";
            confirmBetButton.interactable = !_betPlaced && _pendingBids.Count > 0;
        }
        else
        {
            timerText.text = "0";
            statusText.text = "Spinning…";
            confirmBetButton.interactable = false;
        }
    }

    // ── Betting ─────────────────────────────────────────────────────────────

    /// <summary>Hook to each number button (0..9).</summary>
    public void OnNumberTapped(int number)
    {
        if (!BingoClock.Instance.IsBetting || _betPlaced) return;

        _pendingBids.TryGetValue(number, out var current);
        _pendingBids[number] = current + chipAmount;
    }

    /// <summary>Hook to the Confirm button.</summary>
    public void OnConfirmBet()
    {
        if (_pendingBids.Count == 0 || _betPlaced) return;
        StartCoroutine(PlaceBetRoutine());
    }

    private IEnumerator PlaceBetRoutine()
    {
        // Capture the slot at submit time. If the round rolls over in flight the
        // server returns 409 and we simply drop the bet rather than betting on
        // a round the player never saw.
        var slotNo = BingoClock.Instance.SlotNo;

        confirmBetButton.interactable = false;

        yield return BingoApi.PlaceBid(slotNo, _pendingBids,
            data =>
            {
                _betPlaced = true;
                _lastSlotId = data.slot_id;
                balanceText.text = "₹" + data.balance;
                _pendingBids.Clear();
                statusText.text = "Bet placed";
            },
            err =>
            {
                statusText.text = err;   // already player-readable
                _pendingBids.Clear();
                StartCoroutine(BingoClock.Instance.Sync());
            });
    }

    // ── Round lifecycle ─────────────────────────────────────────────────────

    private void HandleRoundStarted(long slotNo)
    {
        _betPlaced = false;
        _pendingBids.Clear();
        winningNumberText.text = "";
        StartCoroutine(RefreshBalance());
    }

    private void HandleBettingClosed(long slotNo)
    {
        StartCoroutine(RevealRoutine());
    }

    private IEnumerator RevealRoutine()
    {
        // Let the wheel spin for the configured window before asking the server
        // — the result is only settled once betting has closed.
        yield return new WaitForSeconds(BingoClock.Instance.SpinSeconds);

        if (_lastSlotId < 0) yield break;

        yield return BingoApi.GetResult(_lastSlotId,
            data =>
            {
                winningNumberText.text = data.winning_number.ToString();

                var won = !string.IsNullOrEmpty(data.your_payout) && data.your_payout != "0.00";
                statusText.text = won ? "You won ₹" + data.your_payout : "Better luck next round";

                StartCoroutine(RefreshBalance());
            },
            err => statusText.text = err);
    }

    private IEnumerator RefreshBalance()
    {
        yield return BingoApi.GetBalance(
            balance => balanceText.text = "₹" + balance,
            err => Debug.LogWarning("[Bingo] balance: " + err));
    }
}
