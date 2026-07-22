# Unity integration — Real Bingo

Live API base URL:

```
https://bingo-backend-bjqq.onrender.com/api
```

No API changes were needed. The backend already returns a Unity-friendly
envelope on every endpoint, and CORS is open for `api/*` (verified: preflight
returns 204), so **Android, iOS, desktop and WebGL all work as-is**.

## Files

| File | Drop into | Purpose |
|------|-----------|---------|
| `BingoApi.cs` | `Assets/Scripts/` | All endpoints + response DTOs + token storage |
| `BingoClock.cs` | `Assets/Scripts/` | Local game clock — the important one |
| `BingoGameController.cs` | `Assets/Scripts/` | Reference game loop, wire your UI to it |

## Setup

1. Copy the three `.cs` files into `Assets/Scripts/`.
2. Create an empty GameObject in your first scene, name it `BingoClock`, and
   add the **BingoClock** component. It marks itself `DontDestroyOnLoad`.
3. Add **BingoGameController** to your game scene and wire the UI fields.
4. Player Settings → **Api Compatibility Level: .NET Standard 2.1**.

Nothing else. No packages, no plugins — it is all `UnityWebRequest`.

## The one architectural rule

**Do not poll the server every second.**

The backend derives the round purely from the wall clock:

```
slot_no = floor(unixSeconds / 60)
```

So after a single time sync, Unity computes the round number and the exact
seconds remaining locally. `BingoClock` does this. Every device that has synced
shows the identical second with zero traffic.

```csharp
var clock = BingoClock.Instance;
clock.SlotNo;               // current round, matches the server exactly
clock.IsBetting;            // false during the 5s spin
clock.BettingSecondsLeft;   // what you show on the countdown
```

It resyncs once a minute to correct drift. Polling `/round/current` every second
would defeat the whole design and burn your free-tier instance.

Two events drive the game loop:

```csharp
clock.OnRoundStarted  += slotNo => { /* clear bets, refresh balance */ };
clock.OnBettingClosed += slotNo => { /* spin the wheel, then fetch result */ };
```

## Auth

Token is a Sanctum bearer token, stored in `PlayerPrefs` and attached
automatically. On any `401` the client clears it so you can bounce to login.

```csharp
StartCoroutine(BingoApi.Login("9876543210", "password",
    token => SceneManager.LoadScene("Game"),
    err   => errorText.text = err));
```

`err` is already a human-readable sentence from the server ("Invalid
credentials.", "Betting is closed for this round.") — show it directly.

## Placing a bet

```csharp
var bids = new Dictionary<int, float> { { 3, 100f }, { 7, 50f } };

StartCoroutine(BingoApi.PlaceBid(BingoClock.Instance.SlotNo, bids,
    data => balanceText.text = data.balance,
    err  => statusText.text = err));
```

Send `slot_no` (the clock slot), not `slot_id`. If the round rolls over between
tap and submit the server replies **409** — resync and let the player re-bet.

`/round/{id}/result` takes `slot_id` (the database id), which you get back from
`PlaceBid`. The two ids are different on purpose; mixing them up is the easiest
mistake to make here.

---

## Gotchas that will cost you an afternoon

**Cold start.** The free Render instance sleeps when idle. The first call after
a sleep takes ~50 seconds. `BingoApi.TimeoutSeconds` is 60 for this reason —
show a "Connecting…" state rather than looking frozen. The cron-job.org pinger
keeps it awake, so this mainly bites during quiet testing.

**`winning_number` is `0` when it means "not yet known".** JsonUtility turns
JSON `null` into `0`, and `0` is also a real winning number. Always gate on
`phase == "result"` or `status == "settled"` before showing it. `BingoClock`
gives you `IsBetting` for exactly this.

**Money is a string, not a float.** The backend uses exact decimal maths
(BCMath). `balance`, `amount`, `payout` all arrive as `"100.00"`. Display them
as strings. Parsing to `float` for arithmetic reintroduces the rounding errors
the backend was carefully written to avoid.

**Use `InvariantCulture` for any number you send.** On a device set to a locale
that uses comma decimals, `100.5f.ToString()` produces `"100,5"` and the server
rejects it. `BingoApi` already does this — keep it if you add endpoints.

---

## You cannot log in yet — here is how to test

The production database is fresh: it has your superadmin and the default
settings, and **no players**. Registration is also blocked, because
`sendOtp` returns `null` in production and no SMS provider is wired
(`OtpService::generate` has a `TODO` for it).

**Fastest path — create a test player in the admin panel:**

1. Go to `https://bingo-backend-bjqq.onrender.com/admin` and log in.
2. **Players → New Player**. Fill in:
   - `mobile`: a 10-digit number starting 6–9, e.g. `9876543210`
   - `password`: anything ≥ 6 characters
   - `role`: `player`
   - `status`: `active`
3. Log into Unity with that mobile + password.

Their wallet starts at ₹0, so use **Wallets** in the admin panel to set a
balance for testing.

**Alternative — read the OTP from the logs:** set `LOG_LEVEL=debug` in Render
(it is currently `error`, which filters out the `Log::info` line that contains
the OTP), call `send-otp`, then read the code from the Logs tab and complete a
real registration. Set it back to `error` afterwards.

## Still blocking real players

- **Deposits never credit.** With `APP_ENV=production`, `addAmount()` creates
  the deposit as `pending`, and nothing calls `WalletService::credit` on
  approval. Players can deposit; the money never arrives. Needs a payment
  gateway callback or an admin Approve action.
- **No SMS provider**, so nobody can self-register.
- `max_payout_per_round` is configured but never enforced in `RoundService::settle()`.
- No rate limits on `place-bid`, `add-amount`, `withdraw`, `transfer`.
