# Real Bingo Backend — How to Run & Make Changes

A beginner-friendly guide (you're a Unity/C# dev, so this assumes no PHP background).

---

## 1. Start the server (daily workflow)

1. Open **PowerShell** (or Windows Terminal).
2. Go to the project folder:
   ```
   cd D:\commercialProject\bingo-backend
   ```
3. Start the server:
   ```
   php artisan serve
   ```
   → It runs at **http://127.0.0.1:8000**. Leave this window open while you work.
4. (Optional) So rounds keep advancing on their own, open a **second** PowerShell, `cd` to the same folder, and run:
   ```
   php artisan schedule:work
   ```
5. To **stop** the server: click the window and press **Ctrl + C**.

> If `php` says "not recognized": close and reopen the terminal (PHP was installed via Scoop into your user PATH). If it still fails, the folder `C:\Users\sachi\scoop\shims` must be on your PATH.

**Admin dashboard:** http://localhost:8000/admin
**Superadmin login (dev):** `superadmin@realbingo.test` / `Super@123`
**Create a real superadmin (separate, secure):**
```
php artisan bingo:create-superadmin
```
**Admin roles:** superadmin (owner) → admin → subadmin. Superadmin creates admins;
admins create subadmins; subadmins only see the resources granted to them (set via the
"Subadmin can view" checkboxes when creating/editing a subadmin). Admins live in their
own `admins` table — completely separate from players.
**OTP codes (local):** printed in `storage/logs/laravel.log`

---

## 2. Where everything lives (folder map)

| You want to change... | Edit this |
|------------------------|-----------|
| Game values (payout, timer, bet limits, referral bonus) | `.env` (the `GAME_*` lines) or `config/game.php` |
| API endpoint behaviour | `app/Http/Controllers/Api/*.php` |
| Business rules (wallet, rounds, OTP, referral, withdrawals) | `app/Services/*.php` |
| The list of API URLs | `routes/api.php` |
| Database tables (PHP view) | `app/Models/*.php` |
| Database table structure | `database/migrations/*.php` |
| Admin dashboard pages | `app/Filament/Resources/*` |
| Logs + local OTP codes | `storage/logs/laravel.log` |
| The local database (a single file) | `database/database.sqlite` |

---

## 3. Making a "sudden change" — common examples

### A) Change a game value (most common)
Example: client wants **payout 8× instead of 9×**, and **30-second betting**.
1. Open `.env`, change:
   ```
   GAME_PAYOUT_MULTIPLIER=8
   GAME_BETTING_SECONDS=30
   ```
2. Clear the cached config so the change takes effect:
   ```
   php artisan config:clear
   ```
3. Done. (Restart `php artisan serve` if it was running.)

Other values you can change the same way: `GAME_MIN_BET`, `GAME_MAX_BET_PER_NUMBER`,
`REFERRAL_BONUS`, `GAME_RESULT_SECONDS`, `GAME_NUMBERS`.

### B) Change how an endpoint behaves
Edit the matching controller in `app/Http/Controllers/Api/`, e.g. login rules →
`AuthController.php`, betting → `GameController.php`. Save the file — no rebuild needed,
just refresh/retry the request.

### C) Add a new column to a table
```
php artisan make:migration add_nickname_to_users_table
```
Open the new file in `database/migrations/`, add the column inside `up()`, then:
```
php artisan migrate
```

### D) Always test after a change
```
php artisan test
```
If it says "passed", you're safe. Then save your work (see §5).

---

## 4. Handy commands (cheat sheet)

| Command | What it does |
|---------|--------------|
| `php artisan serve` | Start the local server |
| `php artisan test` | Run all automated tests |
| `php artisan route:list` | Show every API URL |
| `php artisan migrate` | Apply new database changes |
| `php artisan db:seed --class=AdminUserSeeder` | Recreate the admin login |
| `php artisan config:clear` | Apply `.env` / config edits |
| `php artisan optimize:clear` | Clear ALL caches if something seems stuck |
| `php artisan tinker` | Interactive console to inspect data |

⚠️ **Dangerous (wipes ALL data):**
```
php artisan migrate:fresh --seed
```
Only use this to reset the database from scratch in development.

---

## 5. Saving your changes (git)

After any change you're happy with:
```
git add -A
git commit -m "Short note of what you changed"
```
See history:
```
git log --oneline
```
Undo edits to one file you haven't committed yet:
```
git checkout -- path/to/file.php
```

---

## 6. Inspecting the database

- Easiest GUI: install **DB Browser for SQLite**, then open `database/database.sqlite`.
- Or via console:
  ```
  php artisan tinker
  ```
  then for example:
  ```
  \App\Models\User::all();
  \App\Models\Round::latest()->first();
  ```
  Type `exit` to leave.

---

## 7. Golden rules

1. **Test before you trust:** run `php artisan test` after changes.
2. **Commit often:** small commits = easy to undo.
3. **`.env` is local & secret** — it's not in git on purpose. `config/*.php` IS in git.
4. **Don't edit `vendor/`** — that's installed library code.
5. When moving to a real server later, the DB switches from SQLite to MySQL by changing
   `.env` (`DB_CONNECTION=mysql` + host/user/pass) — no code changes needed.
