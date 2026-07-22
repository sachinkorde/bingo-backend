using System;
using System.Collections;
using System.Collections.Generic;
using System.Globalization;
using System.Text;
using UnityEngine;
using UnityEngine.Networking;

/// <summary>
/// Client for the Real Bingo backend.
///
/// Every response uses the same envelope: { success, message, data }.
/// On failure `message` is already a human-readable sentence from the server,
/// so it can be shown to the player directly.
///
/// Usage (from any MonoBehaviour):
///     StartCoroutine(BingoApi.Login("9876543210", "secret",
///         token => Debug.Log("logged in"),
///         err   => Debug.LogError(err)));
/// </summary>
public static class BingoApi
{
    public const string BaseUrl = "https://bingo-backend-bjqq.onrender.com/api";

    /// <summary>
    /// Free-tier Render instances sleep when idle and take ~50s to wake. Keep
    /// this generous or the very first call of a session will time out.
    /// </summary>
    public const int TimeoutSeconds = 60;

    private const string TokenKey = "bingo_token";

    public static string Token
    {
        get => PlayerPrefs.GetString(TokenKey, string.Empty);
        set { PlayerPrefs.SetString(TokenKey, value ?? string.Empty); PlayerPrefs.Save(); }
    }

    public static bool IsLoggedIn => !string.IsNullOrEmpty(Token);

    public static void Logout() => Token = string.Empty;

    // ── Auth ────────────────────────────────────────────────────────────────

    /// <summary>
    /// module = "register" or "forgot_password".
    /// NOTE: in production the server never returns the OTP — it must arrive by
    /// SMS. See unity/README.md, this is currently unwired.
    /// </summary>
    public static IEnumerator SendOtp(string mobile, string module, Action<string> onOk, Action<string> onError)
    {
        var form = new WWWForm();
        form.AddField("mobile", mobile);
        form.AddField("module", module);

        return Post("/send-otp", form, false, json =>
        {
            var res = JsonUtility.FromJson<BaseResponse>(json);
            onOk?.Invoke(res.message);
        }, onError);
    }

    public static IEnumerator Register(string mobile, string password, string otp,
        string email, string referral, Action<string> onToken, Action<string> onError)
    {
        var form = new WWWForm();
        form.AddField("mobile", mobile);
        form.AddField("password", password);
        form.AddField("confirm_password", password);
        form.AddField("otp", otp);
        if (!string.IsNullOrEmpty(email)) form.AddField("email", email);
        if (!string.IsNullOrEmpty(referral)) form.AddField("referral", referral);

        return Post("/register", form, false, json =>
        {
            var res = JsonUtility.FromJson<TokenResponse>(json);
            Token = res.data.token;
            onToken?.Invoke(res.data.token);
        }, onError);
    }

    /// <summary>credential = mobile number or email.</summary>
    public static IEnumerator Login(string credential, string password, Action<string> onToken, Action<string> onError)
    {
        var form = new WWWForm();
        form.AddField("credential", credential);
        form.AddField("password", password);

        return Post("/login", form, false, json =>
        {
            var res = JsonUtility.FromJson<TokenResponse>(json);
            Token = res.data.token;
            onToken?.Invoke(res.data.token);
        }, onError);
    }

    // ── Game ────────────────────────────────────────────────────────────────

    /// <summary>
    /// Full session state INCLUDING server_time. Call this to (re)sync the
    /// clock — then count down locally instead of polling. See BingoClock.
    /// </summary>
    public static IEnumerator GetCurrentRound(Action<RoundData> onOk, Action<string> onError)
    {
        return Get("/round/current", true, json =>
        {
            var res = JsonUtility.FromJson<RoundResponse>(json);
            onOk?.Invoke(res.data);
        }, onError);
    }

    /// <summary>Public, no auth — the cheapest way to resync the clock.</summary>
    public static IEnumerator GetServerTime(Action<RoundData> onOk, Action<string> onError)
    {
        return Get("/server-time", false, json =>
        {
            var res = JsonUtility.FromJson<RoundResponse>(json);
            onOk?.Invoke(res.data);
        }, onError);
    }

    /// <summary>
    /// Place bets. `bids` maps number (0..9) to amount.
    /// `slotNo` must be the slot the player is actually betting on — if the
    /// round rolled over mid-tap the server replies 409 and you should resync.
    /// </summary>
    public static IEnumerator PlaceBid(long slotNo, Dictionary<int, float> bids,
        Action<PlaceBidData> onOk, Action<string> onError)
    {
        var form = new WWWForm();
        form.AddField("slot_no", slotNo.ToString(CultureInfo.InvariantCulture));
        form.AddField("bids", BuildBidsJson(bids));

        return Post("/place-bid", form, true, json =>
        {
            var res = JsonUtility.FromJson<PlaceBidResponse>(json);
            onOk?.Invoke(res.data);
        }, onError);
    }

    /// <summary>slotId is `slot_id` from the round payload, NOT slot_no.</summary>
    public static IEnumerator GetResult(int slotId, Action<ResultData> onOk, Action<string> onError)
    {
        return Get("/round/" + slotId + "/result", true, json =>
        {
            var res = JsonUtility.FromJson<ResultResponse>(json);
            onOk?.Invoke(res.data);
        }, onError);
    }

    public static IEnumerator GetHistory(Action<int[]> onOk, Action<string> onError)
    {
        return Get("/round/history", true, json =>
        {
            var res = JsonUtility.FromJson<HistoryResponse>(json);
            onOk?.Invoke(res.data.history);
        }, onError);
    }

    // ── Wallet ──────────────────────────────────────────────────────────────

    public static IEnumerator GetBalance(Action<string> onOk, Action<string> onError)
    {
        return Get("/wallet/balance", true, json =>
        {
            var res = JsonUtility.FromJson<BalanceResponse>(json);
            onOk?.Invoke(res.data.balance);
        }, onError);
    }

    public static IEnumerator Transfer(string recipient, float amount, Action<string> onOk, Action<string> onError)
    {
        var form = new WWWForm();
        form.AddField("recipient", recipient);
        form.AddField("amount", amount.ToString("0.00", CultureInfo.InvariantCulture));

        return Post("/transfer", form, true, json =>
        {
            var res = JsonUtility.FromJson<BaseResponse>(json);
            onOk?.Invoke(res.message);
        }, onError);
    }

    // ── Transport ───────────────────────────────────────────────────────────

    private static IEnumerator Get(string path, bool auth, Action<string> onJson, Action<string> onError)
    {
        using var req = UnityWebRequest.Get(BaseUrl + path);
        yield return Dispatch(req, auth, onJson, onError);
    }

    private static IEnumerator Post(string path, WWWForm form, bool auth, Action<string> onJson, Action<string> onError)
    {
        using var req = UnityWebRequest.Post(BaseUrl + path, form);
        yield return Dispatch(req, auth, onJson, onError);
    }

    private static IEnumerator Dispatch(UnityWebRequest req, bool auth, Action<string> onJson, Action<string> onError)
    {
        req.timeout = TimeoutSeconds;
        req.SetRequestHeader("Accept", "application/json");
        if (auth && IsLoggedIn) req.SetRequestHeader("Authorization", "Bearer " + Token);

        yield return req.SendWebRequest();

        var body = req.downloadHandler != null ? req.downloadHandler.text : null;

        // No body at all -> genuine connectivity failure (or the free instance
        // is still cold-starting).
        if (string.IsNullOrEmpty(body))
        {
            onError?.Invoke(req.result == UnityWebRequest.Result.Success
                ? "Empty response from server."
                : "Cannot reach the server. Check your connection and try again.");
            yield break;
        }

        BaseResponse envelope;
        try { envelope = JsonUtility.FromJson<BaseResponse>(body); }
        catch (Exception) { onError?.Invoke("Unexpected response from server."); yield break; }

        // A 401 means the stored token is dead — clear it so the UI can bounce
        // the player back to the login screen.
        if (req.responseCode == 401)
        {
            Logout();
            onError?.Invoke(envelope != null && !string.IsNullOrEmpty(envelope.message)
                ? envelope.message
                : "Session expired. Please log in again.");
            yield break;
        }

        if (envelope == null || !envelope.success)
        {
            onError?.Invoke(envelope != null && !string.IsNullOrEmpty(envelope.message)
                ? envelope.message
                : "Request failed.");
            yield break;
        }

        onJson?.Invoke(body);
    }

    /// <summary>
    /// The server accepts `bids` as a JSON string: {"3":100,"7":50}.
    /// InvariantCulture matters — a comma decimal separator would break parsing.
    /// </summary>
    public static string BuildBidsJson(Dictionary<int, float> bids)
    {
        var sb = new StringBuilder("{");
        var first = true;
        foreach (var kv in bids)
        {
            if (kv.Value <= 0f) continue;
            if (!first) sb.Append(',');
            sb.Append('"').Append(kv.Key).Append("\":")
              .Append(kv.Value.ToString("0.00", CultureInfo.InvariantCulture));
            first = false;
        }
        return sb.Append('}').ToString();
    }
}

// ── DTOs ────────────────────────────────────────────────────────────────────
// Unity's JsonUtility needs concrete [Serializable] classes; it cannot do
// generics or dictionaries. Money is returned as a STRING ("100.00") because
// the backend uses exact decimal math — keep it a string, never parse to float
// for display.

[Serializable]
public class BaseResponse
{
    public bool success;
    public string message;
}

[Serializable] public class TokenData { public string token; }
[Serializable] public class TokenResponse : BaseResponse { public TokenData data; }

[Serializable]
public class RoundData
{
    public long server_time;          // ms since epoch, server clock
    public int slot_id;               // DB id — use for /round/{id}/result
    public long slot_no;              // clock slot — use for place-bid
    public string status;             // "betting" | "settled"
    public string phase;              // "betting" | "result"
    public int seconds_left;          // left in the CURRENT phase
    public int session_seconds_left;
    public int winning_number;        // ONLY valid when phase == "result"
    public string server_seed_hash;
    public int numbers;               // how many numbers (10 => 0..9)
    public int payout_multiplier;     // 9
    public int session_seconds;       // 60
    public int betting_seconds;       // 55
    public int spin_seconds;          // 5
}

[Serializable] public class RoundResponse : BaseResponse { public RoundData data; }

[Serializable] public class BalanceData { public string balance; }
[Serializable] public class BalanceResponse : BaseResponse { public BalanceData data; }

[Serializable]
public class PlaceBidData
{
    public int slot_id;
    public string total_bet;
    public string balance;
}

[Serializable] public class PlaceBidResponse : BaseResponse { public PlaceBidData data; }

[Serializable]
public class BetInfo
{
    public int number;
    public string amount;
    public bool is_winner;
    public string payout;
}

[Serializable]
public class ResultData
{
    public int slot_id;
    public string status;
    public int winning_number;
    public string server_seed;        // revealed once settled — provable fairness
    public string server_seed_hash;
    public string your_payout;
    public BetInfo[] bets;
}

[Serializable] public class ResultResponse : BaseResponse { public ResultData data; }

[Serializable] public class HistoryData { public int[] history; }
[Serializable] public class HistoryResponse : BaseResponse { public HistoryData data; }
