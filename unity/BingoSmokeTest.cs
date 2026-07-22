using System.Collections;
using System.Collections.Generic;
using UnityEngine;

/// <summary>
/// Drop this on ANY GameObject and press Play. It verifies, in order:
///   1. the device can reach the server
///   2. login works
///   3. the local clock matches the server
///   4. a bet can be placed
///
/// Watch the Console. Delete this script once everything is green.
/// </summary>
public class BingoSmokeTest : MonoBehaviour
{
    [Header("A player created in /admin -> Players")]
    public string mobile = "9876543210";
    public string password = "";

    [Header("Set false until the wallet has a balance")]
    public bool alsoTryBetting = false;

    private IEnumerator Start()
    {
        Debug.Log("[1/4] Reaching server… (first call can take ~50s on free tier)");

        var reached = false;
        yield return BingoApi.GetServerTime(
            data =>
            {
                reached = true;
                Debug.Log($"[1/4] OK — slot {data.slot_no}, phase '{data.phase}', {data.seconds_left}s left");
            },
            err => Debug.LogError("[1/4] FAILED — " + err));

        if (!reached) yield break;

        if (string.IsNullOrEmpty(password))
        {
            Debug.LogWarning("[2/4] Skipped — set a password in the Inspector.");
            yield break;
        }

        Debug.Log("[2/4] Logging in…");

        var loggedIn = false;
        yield return BingoApi.Login(mobile, password,
            token => { loggedIn = true; Debug.Log("[2/4] OK — token stored"); },
            err => Debug.LogError("[2/4] FAILED — " + err));

        if (!loggedIn) yield break;

        yield return BingoApi.GetBalance(
            balance => Debug.Log("      balance: " + balance),
            err => Debug.LogWarning("      balance failed: " + err));

        Debug.Log("[3/4] Syncing clock…");

        var clock = BingoClock.Instance;
        if (clock == null)
        {
            Debug.LogError("[3/4] FAILED — no BingoClock in the scene. " +
                           "Add an empty GameObject with the BingoClock component.");
            yield break;
        }

        yield return clock.SyncFull();
        Debug.Log($"[3/4] OK — local slot {clock.SlotNo}, betting={clock.IsBetting}, " +
                  $"{clock.BettingSecondsLeft}s left, {clock.Numbers} numbers, " +
                  $"{clock.PayoutMultiplier}x payout");

        if (!alsoTryBetting)
        {
            Debug.Log("[4/4] Skipped — tick 'Also Try Betting' once the wallet has funds.");
            yield break;
        }

        if (!clock.IsBetting)
        {
            Debug.LogWarning("[4/4] Betting is closed right now — press Play again in a few seconds.");
            yield break;
        }

        Debug.Log("[4/4] Placing a test bet on number 3…");

        yield return BingoApi.PlaceBid(clock.SlotNo, new Dictionary<int, float> { { 3, 10f } },
            data => Debug.Log($"[4/4] OK — bet {data.total_bet}, balance now {data.balance}, " +
                              $"slot_id {data.slot_id} (use this for /result)"),
            err => Debug.LogError("[4/4] FAILED — " + err));
    }
}
