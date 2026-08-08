# PRD vs Backend — Gap Analysis

Comparison of the client's Product Requirements Document against the current
Laravel backend. Written for planning and estimation.

**Current state:** 24 API endpoints · 14 business tables · 6 services · 46 tests passing

---

## Scorecard

| PRD Topic | Area | Status |
|---|---|---|
| 3 | Multi-language | ❌ Not started |
| 4 | Auth (username, auto-referral, JWT, single-session) | 🟡 Partial — needs rework |
| 5 | Joker slot + 20× payout | 🟡 Partial — 10 numbers only, single multiplier |
| 8 | 60s loop (55+5) | ✅ **Matches exactly** |
| 8 | Auto-commit at 0s | ❌ Not started |
| 8 | WebSockets | ❌ Uses REST clock-sync (see §6) |
| 9 | Pre-Launch Demo Mode | ❌ Not started |
| 10 | Result algorithms (3 modes) | ⚠️ **Conflicts** with provably-fair engine |
| 11 | Crypto wallet (Mudrex/USDT/TRON) | ❌ Not started |
| 12 | DECIMAL(18,4), frozen balance, txn PIN | 🟡 Partial |
| 13 | Transactions passbook | 🟡 Data exists, no endpoint |
| 14–15 | MLM hierarchy + commission engine | ❌ Not started — largest item |
| 16 | Menu / profile / settings | ✅ Mostly done |
| 17 | Admin panel (14 parts) | 🟡 13 modules exist, needs depth |
| 18 | Security edge cases | 🟡 Partial |
| 19 | Force-update gate | ✅ **Done** |
| 19 | Backups, Redis, dual-node | ❌ Not started |

---

## 1. Existing APIs that must CHANGE

### 1.1 `POST /api/register` — `AuthController::register()`

| PRD requirement | Current behaviour | Change needed |
|---|---|---|
| Username + password on page 2 | No username field at all | Add `username` column, unique validation |
| No manual referral input | Accepts `referral` string from user | Replace with server-side `AGENT_ID` from deferred deep link |
| Capture device fingerprint | Not captured | Add `device_id` param + storage |
| Mandatory 18+ terms checkbox | Not enforced | Add `terms_accepted` validation |
| Set MLM parent node on submit | Only sets flat `referred_by` | Write to hierarchy table |

### 1.2 `POST /api/login` — `AuthController::login()`

- **Username login:** currently matches `mobile` or `email`. Must add `username`.
- **Single-session enforcement:** PRD requires a new login to invalidate the
  previous device's token (Edge Case 1.1). Currently multi-device is allowed —
  `logout-all` exists but nothing auto-revokes. Needs a token-revocation step in
  `login()`.
- **JWT vs Sanctum:** PRD says JWT 9 times. We use Sanctum bearer tokens.
  Functionally equivalent and revocable (JWT is not) — **confirm with client**
  before rewriting.

### 1.3 `POST /api/send-otp` — `AuthController::sendOtp()` + `OtpService`

- PRD: **3 OTPs per number per 15 minutes**; currently `throttle:6,1`.
- PRD: **3 failed attempts → 30-minute lockout**; currently `otp_max_attempts=5`
  with no lockout window.
- PRD: admin-configurable OTP length (4 or 6) and expiry.
- ⚠️ **No SMS provider connected.** `OtpService::generate()` has a `TODO`.

### 1.4 `POST /api/place-bid` — `GameController::placeBid()`

The most-affected endpoint:

- **Joker slot:** validation is `$number < 0 || $number >= $numbers` — rejects
  anything outside 0–9. Needs an 11th option.
- **Per-outcome multipliers:** `RoundService::settle()` uses one flat
  `payout_multiplier`. Joker needs 20× while numbers stay 9×.
- **Auto-commit:** PRD requires unconfirmed bets to auto-submit at 0s.
- **Live balance check per tap:** PRD wants a WebSocket balance check on every
  chip placement; we only validate at submit.
- **Commission split:** every settled bet must fan out 55/15/20/5/5 to the upline.

### 1.5 `POST /api/withdraw` — `WalletController::withdraw()`

| PRD | Current |
|---|---|
| Min ₹500, **max ₹50,000** per transaction | Min 1, **no maximum** |
| USDT TRC-20 address input | Uses saved bank/UPI details |
| `frozen_balance` row state | Debits straight from balance |
| **No KYC required** (explicitly bypassed) | **Requires verified KYC** |
| Block if balance negative | No negative-balance concept |

⚠️ Note the KYC line is a direct conflict — see §7.

### 1.6 `POST /api/add-amount` — `WalletController::addAmount()`

Currently creates a `pending` deposit for admin approval. PRD replaces this
entirely with a crypto flow: user submits a TxID hash → backend verifies via
webhook → credits after 19 blockchain confirmations. Min ₹1,000.

### 1.7 `POST /api/transfer` — `WalletController::transfer()`

Closest match to the PRD already. Still needs:

- **4-digit Secure Transaction PIN** verification (not implemented)
- Min ₹100 (currently `TRANSFER_MIN=10`)
- **No maximum** (we cap at `TRANSFER_MAX_PER_DAY=50000`) — PRD says unlimited
- Block outbound when balance ≤ 0

✅ Already compliant: anti-self-transfer, recipient name lookup before sending,
atomic debit/credit, row locking.

---

## 2. NEW APIs required

| Endpoint | Purpose | PRD |
|---|---|---|
| `GET /api/transactions` | Passbook: 4 types, filter tabs, 20/page pagination | 13 |
| `POST /api/transaction-pin` | Set/change the 4-digit PIN | 12 |
| `POST /api/referral/generate-link` | Create single-use tokenised invite for a mobile number | 14 |
| `GET /api/commissions` | Live per-downline commission breakdown | 16 |
| `GET /api/content/how-to-play` | Admin-editable content + video URL | 16 |
| `GET /api/content/faq` | Admin-editable FAQ + 4 video URLs | 16 |
| `GET /api/languages` · `POST /api/language` | Localisation | 3 |
| `POST /api/deposit/submit-txid` | Submit crypto TxID hash | 11 |
| `POST /api/webhook/mudrex` | **Public**, signature-verified deposit confirmation | 11 |
| `GET /api/demo-status` | Demo mode flag + balance rules | 9 |

---

## 3. Database changes

### 3.1 New tables

| Table | Purpose |
|---|---|
| `mlm_nodes` | Parent/child hierarchy, tier, path for recursive lookups |
| `commissions` | Per-round, per-tier ledger of the 5-way split |
| `referral_tokens` | Single-use invite tokens with expiry + target mobile |
| `device_fingerprints` | Anti-fraud registry + whitelist |
| `crypto_deposits` | TxID, confirmations, `UNIQUE` on `tx_hash` |
| `app_content` | Admin-editable How to Play / FAQ text + video URLs |
| `languages` / `translations` | Localisation strings |
| `admin_activity_logs` | Immutable audit trail of every admin action |

### 3.2 Column additions

| Table | Add |
|---|---|
| `users` | `username` (unique), `tier`, `parent_id`, `device_id`, `transaction_pin` (hashed), `language` |
| `wallets` | `frozen_balance`, `playable_balance`, `withdrawable_balance` |
| `rounds` | `algorithm_mode` (which of the 3 modes decided the result) |
| `bets` | support for the Joker outcome |

### 3.3 ⚠️ `DECIMAL(18,2)` → `DECIMAL(18,4)`

More invasive than it looks. Affects **7 columns**, but also **12 BCMath calls
and 9 `number_format()` calls with the scale hardcoded to `2`**, across 10 files
including `WalletService` and `RoundService`.

This is money code — it needs a migration, a sweep of every arithmetic call, and
a full re-test. **Do not treat as a quick migration.**

---

## 4. Service layer

### 4.1 New services

| Service | Responsibility |
|---|---|
| `MlmService` | Tree placement, cycle prevention, upline resolution |
| `CommissionService` | 5-way split per round, slippage to admin, negative-balance debt recovery |
| `CryptoPaymentService` | Mudrex/TRON integration, webhook verification, confirmations |
| `ResultAlgorithmService` | The 3 result modes + hourly flags + manual override |
| `DemoModeService` | Demo credits, auto-refill, Go-Live migration |
| `ContentService` | How to Play / FAQ / video URL management |

### 4.2 Modified services

**`RoundService`** — the heaviest change:
- `deriveWinningNumber()` → replaced by `ResultAlgorithmService` (⚠️ removes provably-fair)
- `settle()` → per-outcome multipliers (9× / 20×) **and** commission fan-out
- `numbers()` → 11 outcomes instead of 10

**`WalletService`** — balance is no longer one number:
- Split into `playable` / `withdrawable` / `frozen`
- Precision to 4 decimals
- Negative-balance support for agents

**`OtpService`** — rate limiting, lockout window, configurable length, and a real
SMS provider.

**`ReferralService`** — currently pays a flat bonus on first deposit. PRD needs
100 non-withdrawable points per signup plus the whole MLM commission cascade.

---

## 5. Security work (Topic 18)

| Requirement | Status |
|---|---|
| Row-level locking (`SELECT … FOR UPDATE`) | ✅ Already in `WalletService` |
| Anti-self-transfer | ✅ Done |
| Recipient identity confirmation | ✅ Done |
| Idempotency on repeated operations | ✅ Done for deposits |
| Rate limiting | 🟡 Partial — needs OTP-specific windows |
| Single-session JWT enforcement | ❌ Not started |
| `UNIQUE` constraint on `tx_hash` | ❌ Table doesn't exist |
| Device fingerprinting | ❌ Not started |
| Anti-cyclic MLM tree check | ❌ Not started |
| Webhook signature verification | ❌ Not started |
| Immutable admin audit log | ❌ Not started |

---

## 6. Infrastructure decisions needed

**WebSockets.** The PRD assumes them for the countdown, live bet pool, and
commission pushes. Our clock-sync design achieves the same countdown accuracy
with *zero* per-second traffic — every device computes the same second locally
after one sync. WebSockets would genuinely help for the **live betting-pool
display** and **real-time commission updates**, but are unnecessary for the
timer.

→ **Ask the client whether WebSockets are a hard requirement or an assumption.**
If assumed, we keep a cheaper and equally accurate design.

**Stack.** PRD recommends Node.js/Go. We are Laravel + PostgreSQL, working and
tested. Rewriting would discard everything built. → **Confirm this is not a hard
requirement.**

**Also missing:** Redis (session/state), dual-node + load balancer, automated
04:00 UTC backups, hourly ledger tailing.

---

## 7. ⚠️ Conflicts that need a decision before coding

**1. Topic 10 removes provably-fair results.** All three modes pick the winner
*based on how players bet* (Mode 1 = lowest-liability number wins). Our current
engine derives the result from a pre-published seed hash, independent of bets.
These cannot coexist — implementing Topic 10 means deleting the fairness proof,
and our live landing page currently states results "cannot be influenced by how
players bet."

The PRD's own admin spec (Part 2) offers a **"Pure RNG Mode"** toggle. That
option keeps everything we've built and keeps the marketing truthful.

**2. Topic 12 bypasses KYC and TDS.** Direct quote: *"all regulatory tax (TDS)
and KYC identity inputs are totally bypassed."* Our withdrawal flow currently
*requires* verified KYC. This is a legal question, not a technical one.

**3. Topics 14–15 are an MLM.** Agents earn from downline losses (55/15/20/5/5)
and are forced into negative balances when downlines win. Separately regulated
in India.

---

## 8. Suggested sequencing

**Phase 1 — safe, no rework risk regardless of the decisions above**
1. `GET /api/transactions` passbook
2. Auto-commit at 0s
3. Username login + single-session enforcement
4. OTP rate limiting + lockout
5. Admin panel depth (live monitoring, configurable timers/multipliers)
6. Content APIs (How to Play / FAQ / video URLs)
7. Multi-language

**Phase 2 — needs decisions**
8. Joker slot + per-outcome multipliers *(not legally sensitive, but touches money code — do deliberately with tests)*
9. `DECIMAL(18,4)` migration
10. Demo Mode + Go-Live switch

**Phase 3 — blocked on legal/client answers**
11. Result algorithms (Topic 10)
12. MLM + commission engine (Topics 14–15) — **largest single item**
13. Crypto payments (Topic 11)

---

## Bottom line

- **~40% of the PRD is done**, concentrated in core gameplay, auth basics, wallet
  fundamentals, and the admin foundation.
- **The remaining ~60% is the harder 60%**, and over half of it sits in three
  items: the MLM/commission engine, crypto payments, and the result algorithms.
- **Any timeline given before the §7 questions are answered is guesswork** — those
  three decisions can move the estimate by months in either direction.
