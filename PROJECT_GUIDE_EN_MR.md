# Real Bingo Backend — Project & Database Guide
# रिअल बिंगो बॅकएंड — प्रोजेक्ट आणि डेटाबेस मार्गदर्शक

> English first, then मराठी for each section.
> प्रत्येक भागात आधी इंग्रजी, नंतर मराठी.

The backend lives at: **`D:\commercialProject\bingo-backend`**
बॅकएंड येथे आहे: **`D:\commercialProject\bingo-backend`**

---

## 1. How to OPEN the project / प्रोजेक्ट कसा उघडायचा

**EN:**
1. Install **VS Code** (free) if you don't have it.
2. Open VS Code → **File → Open Folder** → choose `D:\commercialProject\bingo-backend`.
3. That's it — you can now see all the code files in the left panel.

**मराठी:**
1. **VS Code** (फ्री) नसेल तर इन्स्टॉल करा.
2. VS Code उघडा → **File → Open Folder** → `D:\commercialProject\bingo-backend` निवडा.
3. झालं — आता डाव्या बाजूला सगळ्या कोड फायली दिसतील.

---

## 2. How to RUN the server / सर्व्हर कसा चालवायचा

**EN:**
1. Open **PowerShell** (or the VS Code terminal: `Terminal → New Terminal`).
2. Type:
   ```
   cd D:\commercialProject\bingo-backend
   php artisan serve
   ```
3. The server runs at **http://127.0.0.1:8000**. Keep this window open.
4. For sessions to keep generating 24/7, open a **second** terminal and run:
   ```
   php artisan schedule:work
   ```
5. Stop with **Ctrl + C**.
6. Dashboard: **http://localhost:8000/admin** → login `superadmin@realbingo.test` / `Super@123`.

**मराठी:**
1. **PowerShell** उघडा (किंवा VS Code मधील terminal: `Terminal → New Terminal`).
2. टाइप करा:
   ```
   cd D:\commercialProject\bingo-backend
   php artisan serve
   ```
3. सर्व्हर **http://127.0.0.1:8000** वर चालू होईल. ही विंडो उघडी ठेवा.
4. गेमचे सेशन सतत (24/7) तयार होण्यासाठी **दुसरी** terminal उघडून हे चालवा:
   ```
   php artisan schedule:work
   ```
5. बंद करण्यासाठी **Ctrl + C**.
6. डॅशबोर्ड: **http://localhost:8000/admin** → लॉगिन `superadmin@realbingo.test` / `Super@123`.

> If `php` is "not recognized": close & reopen the terminal.
> `php` "not recognized" आल्यास: terminal बंद करून पुन्हा उघडा.

---

## 3. WHERE the database is + HOW to SEE the tables / डेटाबेस कुठे आहे + टेबल्स कसे बघायचे

**EN:** The local database is a single file:
```
D:\commercialProject\bingo-backend\database\database.sqlite
```
There are 3 easy ways to look inside it:

**Way A — DB Browser for SQLite (easiest, visual):**
1. Download & install **"DB Browser for SQLite"** (free).
2. Open it → **Open Database** → choose the `database.sqlite` file above.
3. Tab **"Browse Data"** → pick any table from the dropdown (users, wallets, rounds, transfers, …). You'll see all rows like Excel.

**Way B — Built-in commands (no install):**
```
cd D:\commercialProject\bingo-backend
php artisan db:show          # lists all tables + row counts
php artisan db:table users   # shows the columns of the 'users' table
```

**Way C — Tinker (to read live data):**
```
php artisan tinker
```
then for example:
```php
\App\Models\User::all();              // all players
\App\Models\Round::latest()->first(); // latest session
\App\Models\Transfer::all();          // all transfers
```
Type `exit` to leave.

**मराठी:** लोकल डेटाबेस ही एकच फाईल आहे:
```
D:\commercialProject\bingo-backend\database\database.sqlite
```
आत बघण्याचे ३ सोपे मार्ग:

**मार्ग A — DB Browser for SQLite (सर्वात सोपा, व्हिज्युअल):**
1. **"DB Browser for SQLite"** (फ्री) डाउनलोड करून इन्स्टॉल करा.
2. उघडा → **Open Database** → वरील `database.sqlite` फाईल निवडा.
3. **"Browse Data"** टॅब → dropdown मधून कोणतंही टेबल निवडा (users, wallets, rounds, transfers, …). Excel सारख्या सगळ्या rows दिसतील.

**मार्ग B — बिल्ट-इन कमांड (काही इन्स्टॉल नको):**
```
cd D:\commercialProject\bingo-backend
php artisan db:show          # सगळी टेबल्स + किती rows ते दाखवते
php artisan db:table users   # 'users' टेबलचे columns दाखवते
```

**मार्ग C — Tinker (लाइव्ह डेटा वाचण्यासाठी):**
```
php artisan tinker
```
नंतर उदाहरणार्थ:
```php
\App\Models\User::all();              // सगळे खेळाडू
\App\Models\Round::latest()->first(); // शेवटचे सेशन
\App\Models\Transfer::all();          // सगळे ट्रान्सफर
```
बाहेर पडण्यासाठी `exit`.

---

## 4. WHERE the database CODE is / डेटाबेसचा कोड कुठे आहे

**EN:** Three folders matter:
| Folder | What it is |
|--------|-----------|
| `database/migrations/` | **Table structure** — each file = one table (columns, types). This is where tables are defined. |
| `app/Models/` | **PHP version of each table** — used in code (e.g. `User`, `Wallet`, `Round`, `Transfer`). |
| `database/seeders/` | **Starter data** (e.g. the default superadmin, default settings). |

**मराठी:** तीन फोल्डर महत्त्वाचे:
| फोल्डर | काय आहे |
|--------|---------|
| `database/migrations/` | **टेबलची रचना** — प्रत्येक फाईल = एक टेबल (columns, types). टेबल्स इथे तयार होतात. |
| `app/Models/` | **प्रत्येक टेबलची PHP आवृत्ती** — कोडमध्ये वापरतात (उदा. `User`, `Wallet`, `Round`, `Transfer`). |
| `database/seeders/` | **सुरुवातीचा डेटा** (उदा. डीफॉल्ट superadmin, डीफॉल्ट settings). |

---

## 5. WHICH file for WHAT (change map) / कोणती फाईल कशासाठी (बदलाचा नकाशा)

**EN — "If I want to change X, I edit Y":**
| I want to change... | Edit this file |
|---------------------|----------------|
| Game values (payout 9x, timer, min/max bet) | `.env` (GAME_* lines) or `config/game.php` |
| Referral bonus amount | Dashboard → **Settings** (no code) |
| Login / Register / OTP rules | `app/Http/Controllers/Api/AuthController.php` |
| Profile / KYC fields | `app/Http/Controllers/Api/ProfileController.php` |
| Bank details | `app/Http/Controllers/Api/BankController.php` |
| Deposit / Withdraw / Transfer | `app/Http/Controllers/Api/WalletController.php` |
| Betting / sessions / winning number | `app/Http/Controllers/Api/GameController.php` + `app/Services/RoundService.php` |
| Money math (credit/debit/ledger) | `app/Services/WalletService.php` |
| Referral logic | `app/Services/ReferralService.php` |
| The list of API URLs | `routes/api.php` |
| Dashboard screens (Users, Sessions, Admins…) | `app/Filament/Resources/...` |
| A database table's columns | a file in `database/migrations/` (then run `php artisan migrate`) |

**मराठी — "मला X बदलायचं असेल तर Y फाईल बदला":**
| मला बदलायचंय... | ही फाईल बदला |
|------------------|---------------|
| गेम व्हॅल्यूज (payout 9x, टायमर, min/max bet) | `.env` (GAME_* ओळी) किंवा `config/game.php` |
| रेफरल बोनस रक्कम | डॅशबोर्ड → **Settings** (कोड नको) |
| लॉगिन / रजिस्टर / OTP नियम | `app/Http/Controllers/Api/AuthController.php` |
| प्रोफाइल / KYC फील्ड्स | `app/Http/Controllers/Api/ProfileController.php` |
| बँक डिटेल्स | `app/Http/Controllers/Api/BankController.php` |
| डिपॉझिट / विथड्रॉ / ट्रान्सफर | `app/Http/Controllers/Api/WalletController.php` |
| बेटिंग / सेशन / विनिंग नंबर | `app/Http/Controllers/Api/GameController.php` + `app/Services/RoundService.php` |
| पैशांचे गणित (credit/debit/ledger) | `app/Services/WalletService.php` |
| रेफरल लॉजिक | `app/Services/ReferralService.php` |
| API URL ची यादी | `routes/api.php` |
| डॅशबोर्ड स्क्रीन (Users, Sessions, Admins…) | `app/Filament/Resources/...` |
| टेबलचे columns | `database/migrations/` मधील फाईल (नंतर `php artisan migrate`) |

---

## 6. How to make a CHANGE safely / बदल सुरक्षितपणे कसा करायचा

**EN:**
1. Edit the file.
2. If you changed `.env`: run `php artisan config:clear`.
3. If you changed a migration: run `php artisan migrate`.
4. Test it still works: `php artisan test` → should say **passed**.
5. Save your work: `git add -A` then `git commit -m "what I changed"`.

⚠️ NEVER edit the `vendor/` folder (that's library code).
⚠️ `php artisan migrate:fresh` **deletes all data** — only for a fresh dev reset.

**मराठी:**
1. फाईल बदला.
2. `.env` बदलला असेल: `php artisan config:clear` चालवा.
3. migration बदलला असेल: `php artisan migrate` चालवा.
4. सगळं चालू आहे का तपासा: `php artisan test` → **passed** यायला हवं.
5. काम सेव्ह करा: `git add -A` नंतर `git commit -m "काय बदललं"`.

⚠️ `vendor/` फोल्डर कधीही बदलू नका (ती लायब्ररी कोड आहे).
⚠️ `php artisan migrate:fresh` ने **सगळा डेटा डिलीट होतो** — फक्त नवीन dev reset साठी.

---

## Quick table list / टेबल्सची झटपट यादी
`users` (players), `admins` (dashboard operators), `gamers` (profile/KYC),
`wallets` (balance), `wallet_transactions` (money ledger), `bank_details`,
`otps`, `rounds` (60s sessions), `bets`, `deposits`, `withdrawals`,
`transfers`, `settings`, `personal_access_tokens` (login tokens).
