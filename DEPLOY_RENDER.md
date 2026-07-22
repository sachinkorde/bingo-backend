# Deploying Real Bingo — free hosting with a free domain

Render has **no native PHP runtime**, so this project deploys as **Docker**.
Everything needed is already in the repo:

| File | Purpose |
|------|---------|
| `Dockerfile` | Apache + PHP 8.3 (bcmath, pdo_pgsql, intl, gd) |
| `docker/vhost.conf` | Apache vhost serving `/public` |
| `docker/entrypoint.sh` | Binds `$PORT`, migrates, seeds, creates owner, starts Apache |
| `render.yaml` | Free-tier blueprint |
| `.dockerignore` | Keeps `vendor/`, `node_modules/`, `.env`, local SQLite out of the image |

## The free stack

| Piece | Service | Cost |
|-------|---------|------|
| App hosting | Render free web service (Docker) | Free |
| Database | **Neon** free Postgres | Free, no expiry |
| Domain + HTTPS | `your-app.onrender.com` | Free, automatic SSL |
| Round scheduler | cron-job.org free pinger | Free |

**Why Neon and not Render's database?** Render's *free* Postgres is deleted
after about 30 days — you would lose every player, wallet and balance. Neon's
free tier persists. It is one extra signup and one extra copy-paste.

---

## Step 1 — Free Postgres on Neon

1. Go to **neon.tech** → sign up with GitHub (no card).
2. **Create project** → name it `bingo`, pick the region nearest you
   (Singapore/Mumbai).
3. On the dashboard copy the **connection string**. It looks like:
   ```
   postgresql://user:password@ep-xxxx.ap-southeast-1.aws.neon.tech/bingo?sslmode=require
   ```
   Keep it — that is your `DB_URL`.

> Supabase works too; use its "Connection string → URI" value the same way.

## Step 2 — Generate your production key

```bash
php artisan key:generate --show
```

Copy the `base64:...` output. Never reuse your local key, never commit it.

## Step 3 — Push the deployment files

```bash
git add -A && git commit -m "Add free-tier Render deployment" && git push
```

## Step 4 — Create the Render service

1. **dashboard.render.com** → sign up with GitHub.
2. Grant access to your private `bingo-backend` repo ("Only select
   repositories" → pick it).
3. **New → Blueprint** → choose the repo → **Apply**.
   Render reads `render.yaml` automatically.

## Step 5 — Paste the five secrets

Render prompts for the values marked `sync: false`:

| Variable | Value |
|----------|-------|
| `APP_KEY` | the `base64:...` key from step 2 |
| `DB_URL` | the Neon connection string from step 1 |
| `SUPERADMIN_EMAIL` | your admin login email |
| `SUPERADMIN_PASSWORD` | a strong password |
| `APP_URL` | leave blank for now |

Deploy. First build takes ~5–10 minutes.

**The owner account is created automatically on boot** — the free plan has no
Shell tab, so `docker/entrypoint.sh` runs `bingo:create-superadmin` from these
env vars. Migrations and default settings also run there.

## Step 6 — Set APP_URL

You now have `https://bingo-backend-xxxx.onrender.com`. **That is your free
domain, with free HTTPS.**

Web service → **Environment** → set `APP_URL` to that exact URL (with
`https://`, no trailing slash) → Save. Filament builds asset and redirect URLs
from it, so the admin panel misbehaves until this is right.

## Step 7 — Keep it awake + rotate rounds (important)

Render's free plan spins the service down after ~15 minutes idle, and a cold
start takes 30–60 seconds. A free pinger fixes both that and round rotation:

1. Go to **cron-job.org** → sign up free.
2. **Create cronjob**:
   - URL: `https://your-app.onrender.com/api/server-time`
   - Schedule: **every 1 minute**
3. Save and enable.

That endpoint is public and calls the same settlement path as the scheduler, so
rounds keep settling 24/7 *and* the service never sleeps.

> Render's free plan includes ~750 instance-hours/month. A month is ~730 hours,
> so one always-awake free service fits — but only one. Verify current limits.

## Step 8 — Verify

| Check | URL |
|-------|-----|
| Health | `https://your-app.onrender.com/up` |
| Game clock | `https://your-app.onrender.com/api/server-time` |
| Admin login | `https://your-app.onrender.com/admin` |

`seconds_left` should count down between refreshes. Log into `/admin` with the
`SUPERADMIN_EMAIL` / `SUPERADMIN_PASSWORD` you set.

**Then delete `SUPERADMIN_PASSWORD` from the Render environment** — the account
already exists, and leaving the password in the dashboard is unnecessary risk.

## Redeploying

`autoDeploy: true` — every push to `master` redeploys and migrates
automatically. Set `RUN_MIGRATIONS=false` to skip migrations on a deploy.

---

## About "free custom domains"

The honest answer: `*.onrender.com` **is** your free domain, and it comes with
free auto-renewing HTTPS. Truly free custom TLDs are mostly gone — Freenom
stopped issuing free `.tk`/`.ml`/`.ga` domains, and the remaining free options
(`eu.org`, `is-a.dev`) have slow manual approval and usage restrictions.

A real domain costs roughly ₹200–800/year at Namecheap/Porkbun/Cloudflare. Once
you have one, Render supports custom domains on the free plan: web service →
**Settings → Custom Domain**, add a `CNAME` to your `onrender.com` host, and
Render issues the certificate free.

---

## Upgrading later (when it is real)

Free is right for testing, demos and showing a client. For real money:

1. Web service `plan: free` → `starter` (no spin-down, real CPU/RAM).
2. Add a paid database — Neon's paid tier, or add a `databases:` block for
   Render Postgres and swap `DB_URL` for `DB_HOST`/`DB_PORT`/`DB_DATABASE`/
   `DB_USERNAME`/`DB_PASSWORD` (`fromDatabase`).
3. Replace the external pinger with a real Render cron service:
   ```yaml
   - type: cron
     name: bingo-scheduler
     runtime: docker
     dockerfilePath: ./Dockerfile
     dockerCommand: php artisan schedule:run
     schedule: "* * * * *"
     plan: starter
     envVars:
       - key: RUN_MIGRATIONS
         value: "false"
   ```

---

## ⚠️ Before you accept real money

**Deposits do not credit wallets in production.** In
`app/Http/Controllers/Api/WalletController.php`, `addAmount()` marks a deposit
`success` only when `APP_ENV` is *not* `production`. On Render `APP_ENV=production`,
so deposits are created as `pending` — and there is currently **no code path
that credits the wallet when a deposit is approved** (the Filament Deposits
screen only edits the status string; nothing calls `WalletService::credit`).

Players can request a deposit, but the money never reaches their wallet.
You need a payment-gateway callback or an admin "Approve" action before launch.

Also still open from the code review:
- `max_payout_per_round` is configured but never enforced in `RoundService::settle()`.
- No rate limits on `place-bid`, `add-amount`, `withdraw`, `transfer`.
- P2P transfers have no KYC requirement or daily cap.
