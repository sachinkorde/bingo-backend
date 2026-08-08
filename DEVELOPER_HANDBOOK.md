# Real Bingo — Developer Handbook

**Read this first if you are new to the project.**
It assumes you have never seen Laravel before. Nothing is skipped as "obvious".

---

## Table of contents

1. [What this project actually does](#1-what-this-project-actually-does)
2. [Get it running in 10 minutes](#2-get-it-running-in-10-minutes)
3. [Laravel crash course (only what you need)](#3-laravel-crash-course-only-what-you-need)
4. [Project map — what lives where](#4-project-map--what-lives-where)
5. [The database](#5-the-database)
6. [How a request travels through the code](#6-how-a-request-travels-through-the-code)
7. [The three clever parts you must understand](#7-the-three-clever-parts-you-must-understand)
8. [Every file, every function](#8-every-file-every-function)
9. [The admin panel](#9-the-admin-panel)
10. [The public website](#10-the-public-website)
11. ["I want to change X" — a lookup table](#11-i-want-to-change-x--a-lookup-table)
12. [Testing](#12-testing)
13. [Deployment](#13-deployment)
14. [Gotchas that will confuse you](#14-gotchas-that-will-confuse-you)

---

## 1. What this project actually does

Real Bingo is a **real-money number betting game** for Android.

The game in one paragraph: every **60 seconds** a new round starts. For the first
**55 seconds** players bet on numbers **0 to 9**. Then betting locks, a wheel
spins for **5 seconds**, and one number wins. If you bet on the winning number,
you get **9× your stake**. Then it immediately starts again, 24 hours a day.

This repository is **the backend** — it is three things in one Laravel project:

| Part | What it is | Who uses it |
|------|-----------|-------------|
| **REST API** | 24 JSON endpoints | The Unity Android app |
| **Admin panel** | Web dashboard at `/admin` | The business owner / operators |
| **Public website** | Landing page + legal pages + APK download | Players, before they install |

The Unity game itself is a **separate project** at
`D:\UnityProjects\CommercialProjects\Real Bingo - Copy`. It contains no game
logic — it is a display layer. **All rules, all money, all results are decided
here on the server.** This is deliberate: a player can modify their phone, but
they cannot modify our server.

---

## 2. Get it running in 10 minutes

You need **PHP 8.4+** and **Composer**. Node is only needed if you change the
website's CSS/JS.

```bash
cd D:\commercialProject\bingo-backend

composer install          # download PHP libraries into vendor/
cp .env.example .env      # create your local config file
php artisan key:generate  # generate the app encryption key
php artisan migrate       # create all database tables
php artisan db:seed       # add the default admin + settings + demo players
```

Then start it:

```bash
php artisan serve
```

- API base URL: `http://127.0.0.1:8000/api`
- Admin panel: `http://127.0.0.1:8000/admin`
- Website: `http://127.0.0.1:8000`

**Local admin login:** `superadmin@realbingo.test` / `Super@123`
(This exists **only locally**. Production uses different credentials — see
section 13.)

Open a **second terminal** and run this so game rounds keep advancing even when
nobody is playing:

```bash
php artisan schedule:work
```

---

## 3. Laravel crash course (only what you need)

Laravel is a PHP framework. Five ideas cover 95% of this project.

### 3.1 Routes — the list of URLs

A **route** maps a URL to a piece of code.

```php
// routes/api.php
Route::post('login', [AuthController::class, 'login']);
```

This means: *when someone sends a POST request to `/api/login`, run the `login`
method inside `AuthController`.*

Every API URL in this project is listed in **`routes/api.php`**. If you want to
know what endpoints exist, that one file is the complete answer.

### 3.2 Controllers — receive the request, send a response

A **controller** is a class whose methods handle requests. It should be thin:
validate the input, call a service to do the real work, return JSON.

```php
public function balance(Request $request, WalletService $wallets)
{
    return $this->ok(['balance' => $wallets->balance($request->user())]);
}
```

`$request->user()` is the logged-in player — Laravel figures this out from the
token the app sends.

### 3.3 Models — one PHP class per database table

A **model** represents a table. `App\Models\User` = the `users` table.

```php
User::where('mobile', '9876543210')->first();  // SELECT * FROM users WHERE mobile=... LIMIT 1
$user->wallet;                                  // the related row from `wallets`
```

You almost never write SQL. Models live in **`app/Models/`**.

**Relationships** connect tables:

```php
public function wallet(): HasOne { return $this->hasOne(Wallet::class); }
```

This says "one user has one wallet", letting you write `$user->wallet->balance`.

### 3.4 Migrations — your database schema, as code

A **migration** is a PHP file that creates or changes a table. They live in
**`database/migrations/`** and run in filename order (which is why they start
with a date).

**Never change the database by hand.** Write a migration, run `php artisan
migrate`. That way every developer and the production server get the exact same
schema.

### 3.5 Services — where the real logic lives

Not a Laravel built-in, but a convention this project follows strictly. A
**service** is a plain class holding business rules, so controllers stay simple
and the same logic can be reused from the API, the admin panel, and tests.

They live in **`app/Services/`**. **This is the most important folder in the
project.**

### 3.6 Artisan — the command line tool

```bash
php artisan route:list        # show every URL in the project
php artisan migrate           # apply new migrations
php artisan tinker            # interactive PHP console with your app loaded
php artisan test              # run all tests
php artisan config:clear      # clear cached config (run after editing .env)
```

---

## 4. Project map — what lives where

```
bingo-backend/
├── app/
│   ├── Console/Commands/     # things you run from the terminal
│   ├── Exceptions/           # custom error types
│   ├── Filament/             # the admin panel (auto-generates screens)
│   ├── Http/Controllers/Api/ # API endpoints — thin
│   ├── Models/               # one class per database table
│   ├── Providers/            # app startup configuration
│   ├── Services/             # ⭐ ALL BUSINESS LOGIC LIVES HERE
│   └── Support/              # small helpers (AdminAccess)
│
├── config/
│   ├── game.php              # ⭐ all game rules (payout, timers, limits)
│   └── database.php          # database connection settings
│
├── database/
│   ├── migrations/           # table definitions
│   ├── seeders/              # starter data
│   └── database.sqlite       # ⭐ THE LOCAL DATABASE FILE
│
├── resources/views/          # the public website (Blade templates)
├── routes/
│   ├── api.php               # ⭐ every API endpoint
│   ├── web.php               # website pages
│   └── console.php           # scheduled tasks
│
├── tests/Feature/            # automated tests
├── storage/                  # logs + uploaded files
├── .env                      # ⭐ your local secrets/config (never committed)
└── Dockerfile                # how the server is built for production
```

**If you remember only two things:** business logic is in `app/Services/`, and
game settings are in `config/game.php`.

---

## 5. The database

### 5.1 Where it is

**Locally** it is a single file — no database server to install:

```
D:\commercialProject\bingo-backend\database\database.sqlite
```

That is configured by this line in `.env`:

```
DB_CONNECTION=sqlite
```

**In production** it is a **PostgreSQL** database hosted on Neon
(neon.tech), connected via a `DB_URL` environment variable set in the Render
dashboard. Same code, different database engine — Laravel handles the
difference.

### 5.2 How to look inside it

**Option A — Visual (easiest).** Install **"DB Browser for SQLite"** (free),
open `database/database.sqlite`, click the **Browse Data** tab, pick a table
from the dropdown. It looks like Excel.

**Option B — Terminal, no install:**

```bash
php artisan db:show           # list every table with row counts
php artisan db:table users    # show the columns of one table
```

**Option C — Tinker (read live data with PHP):**

```bash
php artisan tinker
```

```php
App\Models\User::count();                       // how many players
App\Models\User::latest()->first();             // newest player
App\Models\Round::latest('slot_no')->first();   // most recent round
App\Models\WalletTransaction::latest()->take(10)->get();  // last 10 money moves
```

Type `exit` to leave.

### 5.3 Every table explained

**Player-related**

| Table | What it holds |
|---|---|
| `users` | Login identity: mobile, email, password, status, `referral_code` (their own code), `referred_by` (the code they signed up with) |
| `gamers` | Profile + KYC: name, gender, DOB, address, Aadhaar/PAN numbers, uploaded document paths, `kyc_status` (`pending`/`verified`/`rejected`). One row per user |
| `bank_details` | Where a player gets paid: account number, IFSC, UPI id |
| `otps` | One-time passwords. Stores a **hash** of the code, never the code itself |
| `personal_access_tokens` | Login tokens (Sanctum). One row per logged-in device |

**Money-related**

| Table | What it holds |
|---|---|
| `wallets` | The current balance. One row per user |
| `wallet_transactions` | ⭐ **The ledger.** Every single money movement ever, with the balance after it. This is the source of truth for auditing |
| `deposits` | Money-in requests (`pending` → `success`/`failed`) |
| `withdrawals` | Money-out requests (`pending` → `paid`/`rejected`) |
| `transfers` | Player-to-player point transfers |

**Game-related**

| Table | What it holds |
|---|---|
| `rounds` | One row per 60-second round: `slot_no`, `winning_number`, `server_seed`, `server_seed_hash`, totals |
| `bets` | One row per bet: which round, which user, which number, amount, payout, `is_winner` |

**System**

| Table | What it holds |
|---|---|
| `admins` | Dashboard operators. **Separate from `users`** — players can never log into the admin panel |
| `settings` | Values an admin can change without a developer (e.g. `referral_bonus`) |
| `app_versions` | Released APK builds, for the download page and the force-update check |
| `cache`, `jobs`, `sessions` | Laravel internals — you can ignore these |

### 5.4 The golden rule about money

**Never write to `wallets` directly.** Always go through `WalletService`
(section 8.2). It updates the balance *and* writes the ledger row *and* locks
the row so two simultaneous requests cannot corrupt the balance. Bypassing it
will eventually lose someone's money.

---

## 6. How a request travels through the code

Follow one real example: **the player places a bet.**

```
1. Unity app sends:  POST /api/place-bid   { slot_no: 29758351, bids: {"3":100} }
                     Header: Authorization: Bearer <token>
                              │
2. routes/api.php  ───────────┤  matches the route, checks the token is valid
                              │  (middleware 'auth:sanctum')
                              ▼
3. GameController::placeBid() ─  validates input, checks betting is still open,
                                 checks min/max bet limits
                              │
                              ▼
4. RoundService::currentSession() ─ which round are we in right now?
                              │
                              ▼
5. WalletService::debit() ────  takes the money, writes a ledger row,
                                 all inside a locked DB transaction
                              │
                              ▼
6. Bet::create() ─────────────  saves one row per number bet on
                              │
                              ▼
7. Controller::ok() ──────────  returns { success: true, message: "...", data: {...} }
```

**Every response has the same shape**, defined in
`app/Http/Controllers/Controller.php`:

```json
{ "success": true,  "message": "Bets placed.", "data": { ... } }
{ "success": false, "message": "Betting is closed for this round.", "data": null }
```

The Unity app relies on this. `message` is always written in plain English so it
can be shown directly to the player.

---

## 7. The three clever parts you must understand

These are the non-obvious design decisions. Understanding them prevents you from
"fixing" something that isn't broken.

### 7.1 Rounds come from the clock, not from a timer

There is no background job creating rounds. Instead:

```php
slot_no = floor(current_unix_time / 60)
```

Every round has a number derived purely from the current time. Round
`29758351` started at exactly `29758351 × 60` seconds after 1970.

**Why this is clever:** the Unity app syncs the server time **once**, then
calculates the round number and the countdown **locally**. No per-second network
requests. Every phone in the country shows the identical second, and the server
does almost no work.

Code: `RoundService::currentSlotNo()`, `slotStart()`, `timingFor()`.

**Do not replace this with a polling timer.** It would be slower, more expensive,
and less accurate.

### 7.2 Results are provably fair

Players must be able to trust the game without trusting us. So:

1. When a round is created, the server generates a secret random string
   (`server_seed`) and immediately publishes its SHA-256 fingerprint
   (`server_seed_hash`).
2. The winning number is calculated **from that seed** —
   `hash(seed + slot_no) % 10`.
3. After the round settles, the `server_seed` is revealed.

A player can hash the revealed seed themselves and compare it to the fingerprint
published *before* betting opened. If they match, the result was fixed before
anybody bet and could not have been changed.

**Critically: the winning number does not depend on how players bet.** The house
edge comes from the payout maths (9× on a 1-in-10 chance ≈ 10% edge), not from
manipulating results.

Code: `RoundService::deriveWinningNumber()` and `newSessionAttributes()`.

### 7.3 Money uses string maths, never floats

In PHP, `0.1 + 0.2 !== 0.3`. Floating-point rounding errors are unacceptable for
real money. So all arithmetic uses **BCMath**, which works on strings:

```php
bcadd('100.50', '25.25', 2)   // '125.75' — exact, always
```

Balances are stored as `DECIMAL(18,2)` and returned from the API as **strings**
(`"1500.00"`). The Unity client also treats them as strings for display.

Additionally, every balance change happens inside a database transaction with
`lockForUpdate()`, so two simultaneous requests cannot both read the old balance
and overwrite each other.

Code: `WalletService`.

---

## 8. Every file, every function

### 8.1 `app/Http/Controllers/Controller.php` — the base class

Every API controller extends this. Two methods:

| Method | Purpose |
|---|---|
| `ok($data, $message, $extra, $code)` | Return a success response in the standard envelope |
| `fail($message, $code, $data)` | Return an error response in the same envelope |

---

### 8.2 `app/Services/WalletService.php` ⭐ MOST IMPORTANT FILE

**The only place money is allowed to change.** Every rupee that moves anywhere in
this system goes through here.

| Method | What it does |
|---|---|
| `getOrCreate(User $user)` | Returns the user's wallet, creating it with the starting balance if missing |
| `credit($user, $amount, $type, $reference, $meta)` | **Adds** money. Locks the wallet row, updates balance, writes a ledger entry. Returns the `WalletTransaction` |
| `debit($user, $amount, $type, $reference, $meta)` | **Removes** money. Same as above, but throws `InsufficientBalanceException` if the balance is too low |
| `balance(User $user)` | Returns the current balance as a string |
| `normalize($amount)` | Private. Formats any input to exactly 2 decimal places |

**`$type`** categorises the movement in the ledger: `deposit`, `withdraw`, `bet`,
`payout`, `transfer_in`, `transfer_out`, `referral_bonus`, `refund`.

**`$reference`** links the entry back to its cause, e.g. `"round:42"`,
`"withdrawal:17"`, `"transfer:9"`. Always set this — it is what makes auditing
possible later.

---

### 8.3 `app/Services/RoundService.php` ⭐ THE GAME ENGINE

| Method | What it does |
|---|---|
| `numbers()` | How many numbers exist (10 → 0–9). From `config/game.php` |
| `sessionSeconds()` | Total round length (60) |
| `bettingSeconds()` | How long betting stays open (55) |
| `currentSlotNo()` | **The current round number**, from the clock: `intdiv(now, 60)` |
| `slotStart($slotNo)` | The exact `Carbon` timestamp a given round started |
| `currentSession()` | ⭐ The main entry point. Settles any finished rounds, makes sure the current round row exists, returns it |
| `settleIfBettingClosed($session)` | Settles a round the moment betting ends (so winners are paid at reveal time, not later) |
| `ensureSession($slotNo)` | Creates the round row if it doesn't exist yet (`firstOrCreate`) |
| `newSessionAttributes($slotNo)` | Private. Builds a new round: generates the secret seed and publishes its hash |
| `settleDueSessions($currentSlotNo)` | Settles every unsettled round older than now — catches up if the server was down |
| `timingFor($session)` | Returns `['phase' => 'betting'\|'result', 'phase_seconds_left', 'session_seconds_left']` |
| `isBettingOpen($session)` | `true` if we are still in the 55-second betting window |
| `winningNumberFor($session)` | The winning number, or `null` if betting is still open (so it can't leak early) |
| `settle(Round $round)` | ⭐ Calculates the winner, pays everyone who bet on it, marks the round settled. Runs in a locked DB transaction and is safe to call twice |
| `deriveWinningNumber($round)` | The provably-fair calculation: `hexdec(substr(sha256(seed:slot), 0, 8)) % numbers` |

---

### 8.4 `app/Services/OtpService.php`

| Method | What it does |
|---|---|
| `generate($mobile, $module)` | Creates a 6-digit OTP, stores it **hashed** with a 5-minute expiry, writes it to the log, returns the plain code |
| `verify($mobile, $module, $otp)` | Checks the code: not expired, not already used, under the attempt limit. Marks it consumed on success |

`$module` is `register` or `forgot_password`, so a code for one cannot be used
for the other.

⚠️ **There is no SMS provider connected.** `generate()` has a `TODO` where an SMS
gateway (MSG91, Twilio…) needs to be plugged in. Right now the code only goes to
the log file.

---

### 8.5 `app/Services/ReferralService.php`

| Method | What it does |
|---|---|
| `rewardInviterOnFirstDeposit(User $invitee)` | Pays the inviter their bonus — but only on the invitee's **first successful deposit**, never at signup |

Three protections built in: the bonus only fires on the first deposit (stops fake
signups), it checks the ledger for an existing `referral_bonus` with the same
reference (stops double payment), and the amount comes from `settings` so an
admin can change it without a developer.

---

### 8.6 `app/Services/DepositService.php`

| Method | What it does |
|---|---|
| `approve(Deposit $deposit, $providerRef)` | Marks the deposit successful, **credits the wallet**, fires the referral bonus. Re-reads the row under a lock so double-clicking cannot credit twice |
| `reject(Deposit $deposit, $remark)` | Marks it failed. Nothing to refund — a pending deposit was never credited |

---

### 8.7 `app/Services/WithdrawalService.php`

| Method | What it does |
|---|---|
| `approve(Withdrawal $w, $adminId, $providerRef)` | Marks it paid. **The actual bank payout is still a TODO** — no payment provider is connected |
| `reject(Withdrawal $w, $adminId, $remark)` | **Refunds** the held money back to the wallet, marks it rejected |

Remember: money is debited when the player *requests* a withdrawal, not when it
is approved. That is why rejection must refund.

---

### 8.8 `app/Http/Controllers/Api/AuthController.php`

| Method | Endpoint | What it does |
|---|---|---|
| `sendOtp()` | `POST /send-otp` | Generates an OTP. Checks the mobile exists (forgot password) or doesn't (register) |
| `register()` | `POST /register` | Verifies OTP, creates user + gamer + wallet in one transaction, generates a referral code, returns a login token |
| `login()` | `POST /login` | Accepts mobile **or** email in the `credential` field. Returns a token |
| `forgotPassword()` | `POST /forgot-password` | Verifies OTP, returns a short-lived token authorising a password reset |
| `resetPassword()` | `POST /reset-password` | Sets the new password, then deletes the token so it can't be reused |
| `logout()` | `POST /logout` | Deletes the current device's token |
| `logoutAll()` | `POST /logout-all` | Deletes every token for the user |
| `generateReferralCode()` | — | Private. Makes a unique `TRB#####` code |

---

### 8.9 `app/Http/Controllers/Api/GameController.php`

| Method | Endpoint | What it does |
|---|---|---|
| `currentRound()` | `GET /round/current` | Everything the app needs: server time, round number, phase, seconds left, payout multiplier, seed hash |
| `serverTime()` | `GET /server-time` | **Public** (no login). Lightweight clock sync |
| `placeBid()` | `POST /place-bid` | Validates numbers and limits, then debits and creates bet rows **atomically** |
| `result()` | `GET /round/{slotId}/result` | The winning number, your payout, and the revealed seed for verification |
| `history()` | `GET /round/history` | Last 10 winning numbers |
| `parseBids()` | — | Private. Accepts several JSON shapes the app might send and normalises to `[number => amount]` |

---

### 8.10 `app/Http/Controllers/Api/WalletController.php`

| Method | Endpoint | What it does |
|---|---|---|
| `balance()` | `GET /wallet/balance` | Current balance |
| `addAmount()` | `POST /add-amount` | Creates a deposit. ⚠️ In production it is `pending` until an admin approves it |
| `withdraw()` | `POST /withdraw` | Requires **verified KYC + bank details**. Holds the money and creates a pending request |
| `lookupRecipient()` | `GET /transfer/lookup` | Shows who you're about to pay (masked mobile) **before** you pay them |
| `transfer()` | `POST /transfer` | Sends points to another player. Enforces min amount and daily cap |
| `findRecipient()` | — | Private. Finds a user by mobile, email, or username (referral code) |
| `maskMobile()` | — | Private. `9876543210` → `98••••3210` |

---

### 8.11 Other API controllers

**`ProfileController`** — `show()` (get profile + KYC status), `update()` (edit
profile, upload Aadhaar/PAN/photo), `payload()` (private, builds the response),
`fileUrl()` (private, turns a stored path into a public URL).

**`BankController`** — `show()`, `update()`, `payload()`.

**`ReferralController`** — `summary()` (code, bonus, totals), `list()` (who you
referred and whether you've been paid), `maskMobile()`.

**`AppVersionController`** — `show()`. **Public**, because a build old enough to
be blocked may not be able to log in.

---

### 8.12 Models — the notable methods

Most models just declare relationships. These have real logic:

**`User`**
`gamer()`, `wallet()`, `bankDetail()`, `walletTransactions()`, `bets()`,
`deposits()`, `withdrawals()`, `referrals()` — relationships.
`roundsPlayed()`, `roundsWon()`, `winRate()`, `totalWagered()`, `totalWon()`,
`netProfit()`, `referralEarnings()` — statistics used by the admin panel.
`suspicionReason()` / `isSuspicious()` — flags players with an implausible win
rate or very large net winnings, for manual review.

**`Admin`**
`isSuperadmin()`, `isAdmin()`, `isSubadmin()`, `canManageAdmins()`,
`canViewResource($key)` — permission checks.
`canAccessPanel()` — Filament calls this to decide who may log in.

**`Round`** — `bets()`, `isBettingOpen()`, `earning()` (house profit for that round).

**`Gamer`** — `isKycVerified()`.

**`Otp`** — `isExpired()`, `isConsumed()`.

**`Setting`** — `get($key, $default)` and `put($key, $value)`, both static.
Admin-editable values.

**`AppVersion`** — `current()` (static; the active release with the highest
version code) and `resolvedDownloadUrl()` (an external link wins over an
uploaded file).

---

### 8.13 Console commands

| Command | What it does |
|---|---|
| `php artisan game:rotate-rounds` | Settles finished rounds and opens the current one. Scheduled every minute in `routes/console.php` so the game runs 24/7 with no players online |
| `php artisan bingo:create-superadmin` | Creates or updates the owner account. The only way a superadmin is made |

---

## 9. The admin panel

Built with **Filament**, which generates CRUD screens from PHP classes. Each
resource lives in `app/Filament/Resources/<Name>/` and has four parts:

```
UserResource.php          # navigation, icon, permissions
Schemas/UserForm.php      # the create/edit form fields
Tables/UsersTable.php     # the list view columns and row actions
Pages/                    # List / Create / Edit page classes
```

**Modules:** Players, Gamers (KYC), Wallets, Wallet Transactions, Bank Details,
Rounds, Bets, Deposits, Withdrawals, Transfers, Settings, Admins, App Releases.

**Important actions:**

- **Gamers → Verify KYC** — one click. Required before a player can withdraw
- **Deposits → Approve** — ⭐ **this is what actually credits the wallet**
- **Withdrawals → Approve / Reject** — reject automatically refunds
- **App Releases** — publish an APK build

**Permissions** are in `app/Support/AdminAccess.php`. Superadmins see
everything; subadmins only see the resources listed in their `permissions`
column.

---

## 10. The public website

Blade templates in `resources/views/`, routes in `routes/web.php`.

| URL | File | Purpose |
|---|---|---|
| `/` | `landing.blade.php` | Marketing page + APK download |
| `/terms` | `legal/terms.blade.php` | Terms & Conditions |
| `/privacy` | `legal/privacy.blade.php` | Privacy Policy |
| `/refund` | `legal/refund.blade.php` | Refund Policy |
| — | `layouts/site.blade.php` | Shared header/footer/CSS |

All CSS and JS is inline — no external fonts or CDNs, so the page cannot break
because a third party is down.

⚠️ **The three legal pages are drafts.** They contain `[PLACEHOLDER]` values and
a comment at the top saying they need a lawyer's review before launch.

---

## 11. "I want to change X" — a lookup table

| I want to change… | Edit this |
|---|---|
| Payout multiplier, round length, min/max bet | `config/game.php` (or the matching `GAME_*` variable in `.env`) |
| Referral bonus amount | Admin panel → **Settings** (no code needed) |
| Login / registration / OTP rules | `app/Http/Controllers/Api/AuthController.php` |
| Anything about money | `app/Services/WalletService.php` |
| Betting, rounds, the winning number | `app/Services/RoundService.php` |
| Deposit approval behaviour | `app/Services/DepositService.php` |
| The list of API URLs | `routes/api.php` |
| An admin screen | `app/Filament/Resources/…` |
| A database column | Write a **new** migration, then `php artisan migrate` |
| The website | `resources/views/` |

⚠️ After editing `.env`, always run `php artisan config:clear`.
⚠️ **Never edit `vendor/`** — it is third-party code and gets overwritten.
⚠️ `php artisan migrate:fresh` **deletes all data.** Local development only.

---

## 12. Testing

```bash
php artisan test                      # everything (46 tests)
php artisan test --filter=Wallet      # just matching tests
```

Tests live in `tests/Feature/` and use a temporary in-memory database, so they
never touch your real data.

**Always run the tests before pushing.** Several of them exist specifically to
catch money bugs — for example, that approving a deposit twice only credits once,
and that rejecting a withdrawal refunds correctly. If you change anything in
`app/Services/`, the tests are your safety net.

---

## 13. Deployment

Production runs on **Render.com** as a Docker container. See
`DEPLOY_RENDER.md` for the full walkthrough.

- **Push to `master` → Render redeploys automatically.**
- Migrations run automatically on every deploy (`docker/entrypoint.sh`).
- Config lives in Render's **Environment** tab, **not** in `.env` (that file is
  never committed).
- The production admin password comes from the `SUPERADMIN_*` environment
  variables, because the free plan has no shell access.

⚠️ **Render's filesystem is temporary.** Uploaded files are deleted on every
redeploy. That is why APKs are served from a GitHub link instead (see
`build_for_git/README.md`).

---

## 14. Gotchas that will confuse you

**1. Admins are not users.** `users` = players (API only). `admins` = dashboard
operators. Two separate tables, two separate login guards. A player can never
access `/admin`.

**2. Money is returned as a string.** `"1500.00"`, not `1500.00`. Do not parse it
to a float for arithmetic — that reintroduces the rounding errors BCMath exists
to prevent.

**3. `slot_no` ≠ `slot_id`.** `slot_no` is the clock-derived round number (a big
number like `29758351`) and is used for placing bets. `slot_id` is the database
row id (a small number) and is used for fetching results. Mixing them up fails
silently.

**4. Deposits do not credit automatically in production.** `addAmount()` creates
a `pending` deposit. An admin must approve it. This is deliberate — there is no
payment gateway connected yet.

**5. Withdrawals need KYC + bank details.** Otherwise the API returns 403 or 422.
If withdrawal "isn't working", check those two things first.

**6. Rounds settle lazily.** `currentSession()` settles overdue rounds whenever it
is called. The scheduled command exists so this still happens when nobody is
playing.

**7. `winning_number` can be `0`.** Zero is a real winning number, not "no
result". Check the round's `status`/`phase` instead of testing for zero.

**8. Old rounds keep their old results.** Changing `GAME_NUMBERS` does not
recalculate history — settled rounds keep the number they were settled with.

---

## Known gaps (as of this writing)

Things that are **not** built yet, so you don't go looking for them:

- **No SMS provider** — OTPs are only written to the log
- **No payment gateway** — deposits require manual admin approval
- **`max_payout_per_round`** exists in config but is never enforced in `settle()`
- **Legal pages are drafts** and need a lawyer
- The client's PRD asks for several large features that do not exist yet: a Joker
  slot with a 20× payout, an MLM commission structure, crypto payments, and
  multiple languages

---

**Questions this document didn't answer?** Read the code in `app/Services/` —
it is heavily commented, and the comments explain *why*, not just *what*.
