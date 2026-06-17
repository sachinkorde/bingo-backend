# Real Bingo — API Reference

**Base URL (local):** `http://127.0.0.1:8000/api`
(Android emulator: `http://10.0.2.2:8000/api` · real device: `http://<PC-LAN-IP>:8000/api`)

**Auth:** protected endpoints require header `Authorization: Bearer <token>` (Laravel Sanctum).
**Body format:** `form-data` (or `x-www-form-urlencoded`) unless noted.
**Response envelope:** `{ "success": bool, "message": string, "data": {...} }`

**Totals:** 19 endpoints working on the backend · 18 integrated in the Unity client.
Not called by Unity: only `server-time` (used by the dashboard corner clock; the app syncs its clock via `round/current`).

---

## A. Auth

| # | Method | Link | Auth | Parameters | Returns | Unity method |
|---|--------|------|------|------------|---------|--------------|
| 1 | POST | `/api/register` | No | `mobile` (10-digit, starts 6-9), `email` (optional), `password` (min 6), `confirm_password`, `otp`, `referral` (optional) | `data.token` | `APIManager.Register` ✅ |
| 2 | POST | `/api/login` | No | `credential` (mobile or email), `password` | `data.token` | `APIManager.Login` ✅ |
| 3 | POST | `/api/send-otp` | No | `mobile`, `module` (`register` \| `forgot_password`) | success (OTP NOT returned; it's SMS/logged) | `APIManager.SendOTP` ✅ |
| 4 | POST | `/api/forgot-password` | No | `mobile`, `otp` | `data.token` (reset token) | `APIManager.ForgotPassword` ✅ |
| 5 | POST | `/api/reset-password` | Bearer | `password` (min 6), `confirm_password` | success | `APIManager.ResetPassword` ✅ |
| 6 | POST | `/api/logout` | Bearer | — | success | `APIManager.Logout` ✅ |
| 7 | POST | `/api/logout-all` | Bearer | — | success | `APIManager.LogoutAll` ✅ |

## B. Profile & Bank

| # | Method | Link | Auth | Parameters | Returns | Unity method |
|---|--------|------|------|------------|---------|--------------|
| 8 | GET | `/api/profile` | Bearer | — | `data{ id, role, mobile, email, status, referral_code, gamer{ name, gender, date_of_birth, address, adhar_number, pan_number, *_document, profile_image, kyc_status, total_cash, total_bonus } }`, `is_disabled`, `is_deleted` | `APIManager.GetProfile` ✅ |
| 9 | POST | `/api/profile` | Bearer | `name`, `gender` (male/female/other), `date_of_birth`, `address`, `adhar_number`, `pan_number`, `profile_image` (file), `adhar_document` (file), `pan_document` (file) | updated profile | `APIManager.UpdateProfile` ✅ |
| 10 | GET | `/api/bank-detail` | Bearer | — | `data{ account_holder_name, account_number, ifsc_code, upi_id, name, branch, document, status }` | `APIManager.GetBankDetails` ✅ |
| 11 | POST | `/api/bank-detail` | Bearer | `name`, `branch`, `account_holder_name`, `account_number`, `ifsc_code`, `upi_id`, `document` (file) | updated bank detail | `APIManager.EditBankDetails` ✅ |

## C. Wallet

| # | Method | Link | Auth | Parameters | Returns | Unity method |
|---|--------|------|------|------------|---------|--------------|
| 12 | GET | `/api/wallet/balance` | Bearer | — | `data.balance` | `APIManager.GetWalletBalance` ✅ |
| 13 | POST | `/api/add-amount` | Bearer | `amount` (>=1), `source` (optional, e.g. `bank`) | `data{ source, amount, status, balance }` | `APIManager.AddAmount` ✅ |
| 14 | POST | `/api/withdraw` | Bearer | `amount` (>=1), `method` (optional: bank/upi/crypto) | `data{ withdrawal_id, status, balance }` (needs KYC + bank) | `APIManager.Withdraw` ✅ |

## D. Game (sessions, timer, betting)

| # | Method | Link | Auth | Parameters | Returns | Unity method |
|---|--------|------|------|------------|---------|--------------|
| 15 | GET | `/api/server-time` | No | — | `data{ server_time (ms), slot_no, phase, seconds_left, session_seconds_left, session_seconds, betting_seconds }` | ❌ not in Unity (used by **dashboard** corner clock) |
| 16 | GET | `/api/round/current` | Bearer | — | `data{ server_time, slot_id, slot_no, status, phase, seconds_left, session_seconds_left, winning_number, server_seed_hash, numbers, payout_multiplier, session_seconds, betting_seconds, spin_seconds }` | `APIManager.GetCurrentRound` ✅ (timer sync + winning number) |
| 17 | POST | `/api/place-bid` | Bearer | `slot_no` (clock session number), `bids` (JSON: `[{"3":100,"5":50}]`) | `data{ slot_id, total_bet, balance }` | `APIManager.PlaceBid` ✅ |
| 18 | GET | `/api/round/{slotId}/result` | Bearer | path: `slotId` (DB id) | `data{ slot_id, status, winning_number, server_seed, server_seed_hash, your_payout, bets[] }` | `APIManager.GetRoundResult` ✅ |
| 19 | GET | `/api/round/history` | Bearer | — | `data.history` (last 10 winning numbers) | `APIManager.GetRoundHistory` ✅ (seeds the last-10 strip on app start) |

---

## How the timer & winning number work (the optimized flow)

- **Timer:** the client calls `/round/current` **once** (re-syncs every ~2 min), stores
  `offset = server_time - localTime`, then computes the countdown **locally** every tick.
  → same second on every device, **no per-second API calls**.
- **Winning number:** at the end of betting, the client calls `/round/current` **once** to
  read `winning_number`, spins the wheel to it, then calls `/round/{slotId}/result` for the
  payout. The winner is decided **server-side** (fair RNG from a committed seed).

## Sessions
- A session is a fixed **60s** window: `slot_no = floor(unixtime / 60)`.
- Betting first **50s**; spin + reveal last **10s**. Recorded 24/7 by the `game:rotate-rounds` cron.
