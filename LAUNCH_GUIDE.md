# Publication Guide — CURRENT PROJECT → https://copamsterdam.nl

> Companion to [`DEPLOYMENT.md`](DEPLOYMENT.md) (raw VPS/Nginx/Supervisor reference) and [`PLAN.md`](PLAN.md).
> Decisions locked in for this guide: **domains** = `copamsterdam.nl` (canonical) + `copamsterdam.org` (redirects to `.nl`) · **hosting** = Hetzner Cloud VPS provisioned and managed through **Laravel Forge**.
> Nothing in this guide has been purchased or registered on your behalf — every step below is something you click/run yourself. Where money changes hands, it's called out explicitly.

---

## 0. What you're starting from (verified today)

- Laravel 12 (PHP ^8.2) + Inertia.js + Vue 3.5 + TypeScript + Tailwind v4, real-time via Laravel Reverb.
- `php artisan test` → **72/72 passing**. `npm run build` → clean production build.
- Git repo already initialized, clean, pushed to `https://github.com/NanaOseiYaw/church-platform.git` on `main`. `.env` is correctly gitignored and has never been committed.
- `copamsterdam.nl` and `copamsterdam.org` both confirmed **available** via the authoritative RDAP servers (SIDN for `.nl`, Public Interest Registry for `.org`) as of today.
- Fixed already in this session: rate limiting added to login/register/password-reset/contact/prayer/onboarding routes, two dashboard widgets that hard-coded off-brand indigo now use the brand palette, `ChurchSeeder` rebranded from the Ghana demo tenant to an Amsterdam-slugged tenant (still has `TODO:` placeholders for the real assembly's address/phone — see Step 7.3).

---

## STEP 1 — Prepare the project locally

Run these from the project root before touching any server.

```bash
# 1. Install/confirm dependencies
composer install
npm install --legacy-peer-deps

# 2. Full test suite — must be green before you deploy
php artisan test

# 3. Production frontend build — catches Vite/TS errors now, not on the server
npm run build

# 4. Static checks (if configured) — run whatever you have; skip if none
composer run-script phpstan 2>/dev/null || true
```

**What must NOT be committed to Git** (already correctly excluded by `.gitignore`):
`.env`, `.env.backup`, `.env.production`, `/vendor`, `/node_modules`, `/public/build`, `/public/storage`, `/storage/*.key`, `.phpunit.result.cache`.

**Do not seed demo data in production.** You have two seeders:
- `RolesAndPermissionsSeeder` — required, idempotent, run on every fresh install.
- `ChurchSeeder` — creates the one real Amsterdam tenant + admin account. **Edit the `TODO:` placeholders in `database/seeders/ChurchSeeder.php` (address, phone, display name, admin name) with the real assembly's details before you seed production.**
- `DemoSeeder` — **never run this in production.** It's for local demos only (fake members, fake events, fake announcements).

---

## STEP 2 — Git

Already done — you have a GitHub repo (`NanaOseiYaw/church-platform`) on `main`, working tree clean. Nothing to change here except:

```bash
# Confirm before every deploy that you're pushing what you tested
git status
git log --oneline -5
git push origin main
```

If you ever add secrets to a file by mistake, do **not** just delete the line and commit — the secret stays in history. Rotate the credential instead.

---

## STEP 3 — Hosting: Hetzner Cloud + Laravel Forge

**Why this combination for your project specifically:** your app needs a persistent process for Laravel Reverb (WebSockets) and Supervisor-managed queue workers — that rules out pure serverless platforms (Vercel-style, Laravel Vapor) without cutting real-time notifications. A raw VPS gives full control but means you personally own Nginx config, PHP-FPM tuning, SSL renewal, and process supervision. **Forge sits on top of a VPS you still own** and automates all of that through a web dashboard, while leaving Reverb/queue daemons fully configurable — the best fit here.

| | Cost/mo (approx.) | Setup effort | Your ongoing ops burden |
|---|---|---|---|
| Hetzner CX22 VPS + Forge Starter | ~€4.50 (server) + $12 (Forge) ≈ **€15–18** | Moderate (this guide) | Low — Forge handles patching prompts, SSL renewal, deploy scripts |
| Raw VPS, manual (your existing `DEPLOYMENT.md`) | ~€5–10 | High | High — you own everything |
| Laravel Cloud / Vapor | Usage-based, often higher | Low | Low, but **loses Reverb/Supervisor** as built |

Hetzner's Falkenstein/Helsinki datacenters give good latency to Amsterdam and EU data residency, which matters for a Netherlands-based church handling member data (GDPR).

**Action:**
1. Create a Hetzner Cloud account at `https://www.hetzner.com/cloud` (needs a payment method — this is a paid service; confirm you want to proceed before entering card details).
2. Create a Laravel Forge account at `https://forge.laravel.com` (also paid — Starter plan is $12/mo, covers everything here).
3. In Forge → **Server Providers**, connect your Hetzner account via API token (Hetzner Cloud Console → Security → API Tokens → Generate, read+write). Paste that token into Forge.

---

## STEP 4 — Register the domains (TransIP)

TransIP is a Dutch, SIDN-accredited registrar that sells both `.nl` and `.org` under one account with a straightforward DNS panel — good fit since your primary domain is Dutch.

1. Go to `https://www.transip.nl` (or `.eu` for English) and create an account.
2. Search **`copamsterdam.nl`** in the domain checker → it will show available (confirmed today via SIDN RDAP) → add to cart.
3. Search **`copamsterdam.org`** → also available → add to cart.
4. At checkout: choose **1 year** initially (you can set up auto-renew after you've confirmed everything works), fill in registrant details (this becomes the WHOIS/SIDN registrant record — for `.nl` domains SIDN does allow privacy-shielding personal registrant data from public WHOIS; look for that option during checkout).
5. Confirm and pay. **This is the point where money is spent — do this yourself; I cannot complete a purchase for you.**
6. Expect roughly **€8–12/year** for `.nl` and **€12–18/year** for `.org` — confirm exact figures at TransIP's checkout since pricing changes.

You'll come back to TransIP's **DNS** tab in Steps 8 and 12.

---

## STEP 5 — Create the server and connect Forge

1. In Forge, click **Create Server** → provider **Hetzner Cloud**.
2. Choose:
   - **Type:** App Server (PHP + Nginx + MySQL all on one box — fine at church scale; you can split DB out later if needed)
   - **Region:** Falkenstein or Nuremberg (Germany) or Helsinki (Finland) — all low-latency to Amsterdam
   - **Size:** Hetzner **CX22** (2 vCPU / 4GB RAM) is comfortable headroom for this app; CX11 (1 vCPU/2GB) is the bare minimum but leaves little room for the queue worker + Reverb + MySQL running together
   - **PHP version:** 8.3
   - **Database:** MySQL 8
3. Click **Create Server**. Forge provisions Ubuntu, Nginx, PHP-FPM, MySQL, Redis, Supervisor, and a firewall automatically — this replaces steps 2–14 and 21 of the manual VPS process in `DEPLOYMENT.md`. Takes ~5 minutes.
4. Once provisioned, Forge shows you the server's **public IPv4 address** — copy it, you need it for Step 8.

---

## STEP 6 — Create the site and connect the database

1. In Forge, on your new server → **Sites** → **New Site**.
2. **Root domain:** `copamsterdam.nl` (you can add `www.copamsterdam.nl` and `copamsterdam.org` as aliases later — Step 16).
3. **Project type:** General PHP / Laravel.
4. **Web directory:** `/public` (Forge sets this automatically for Laravel).
5. Forge auto-creates a MySQL database and a database user scoped to this site — note the generated database name, username, and password (Forge shows them once; also visible later under the site's **Database** tab). This satisfies "create the production database / user / password" from your checklist — Forge does it for you rather than you hand-running `CREATE DATABASE` over SSH.

---

## STEP 7 — Deploy the application

### 7.1 Connect the Git repository

In the site's **Apps** tab (or **Git Repository** panel):
- Provider: GitHub
- Repository: `NanaOseiYaw/church-platform`
- Branch: `main`
- Install Composer dependencies: **on**

Forge will need permission to your GitHub account/repo — authorize it when prompted (this is a legitimate OAuth grant to your own deployment tool, safe to approve).

### 7.2 Set the deploy script

Forge gives you an editable deploy script box. Replace the default with:

```bash
cd /home/forge/copamsterdam.nl
git pull origin main

$FORGE_COMPOSER install --no-dev --optimize-autoloader

npm ci
npm run build

( flock -w 10 9 || exit 1
    echo 'Restarting FPM...'; sudo -S service $FORGE_PHP_FPM reload ) 9>/tmp/fpmlock

if [ -f artisan ]; then
    $FORGE_PHP artisan migrate --force
    $FORGE_PHP artisan storage:link
    $FORGE_PHP artisan config:cache
    $FORGE_PHP artisan route:cache
    $FORGE_PHP artisan view:cache
    $FORGE_PHP artisan event:cache
    $FORGE_PHP artisan queue:restart
fi
```

> First deploy will run `migrate --force` against an **empty** database — that's expected and safe. On every deploy *after* that, `migrate --force` only applies new migrations; it does not touch existing data. Never run `migrate:fresh` or `migrate:reset` against production — those drop tables.

### 7.3 Environment variables

In the site's **Environment** tab, replace the contents with (fill in the blanks — never share these values with me or anyone else):

```dotenv
APP_NAME="Church of Pentecost Amsterdam"
APP_ENV=production
APP_KEY=                          # leave blank, generate in Step 7.4
APP_DEBUG=false
APP_URL=https://copamsterdam.nl
APP_LOCALE=en

LOG_CHANNEL=stack
LOG_STACK=daily
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=            # from Forge's site Database tab
DB_USERNAME=            # from Forge's site Database tab
DB_PASSWORD=            # from Forge's site Database tab

CACHE_STORE=redis
SESSION_DRIVER=redis
SESSION_LIFETIME=120
SESSION_DOMAIN=.copamsterdam.nl
SESSION_SECURE_COOKIE=true        # REQUIRED: without this the session cookie is also sent over plain HTTP
SESSION_SAME_SITE=lax
QUEUE_CONNECTION=redis

REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

MAIL_MAILER=smtp                           # see Step 12 — mailgun is NOT available in this app
MAIL_HOST=
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@copamsterdam.nl
MAIL_FROM_NAME="Church of Pentecost Amsterdam"

BROADCAST_CONNECTION=reverb
REVERB_APP_ID=                             # any random ID, e.g. output of `openssl rand -hex 8`
REVERB_APP_KEY=                            # `openssl rand -hex 16`
REVERB_APP_SECRET=                         # `openssl rand -hex 32`
REVERB_HOST=copamsterdam.nl
REVERB_PORT=443
REVERB_SCHEME=https
REVERB_SERVER_HOST=0.0.0.0
REVERB_SERVER_PORT=8080

VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"

FILESYSTEM_DISK=local

YOUTUBE_API_KEY=                           # a FRESH key — see security note below

# Initial admin account, read by ChurchSeeder in Step 7.4.
# Leave SEED_ADMIN_PASSWORD blank and the seeder generates a strong random
# password and prints it once — that is the recommended path.
SEED_ADMIN_EMAIL=admin@copamsterdam.nl
SEED_ADMIN_NAME="Church Admin"
SEED_ADMIN_PASSWORD=

CHURCH_NAME="The Church of Pentecost"
CHURCH_TAGLINE="Possessing the Nations"
```

**Security note on the YouTube key:** your local `.env` currently has a live YouTube Data API key. Don't paste that same key into production. In Google Cloud Console → APIs & Services → Credentials, create a **new** key, then under "Application restrictions" set it to **HTTP referrers** and restrict it to `https://copamsterdam.nl/*`. That way, even if it ever leaks, it can't be used from anywhere else.

### 7.4 First deploy

1. Click **Deploy Now** in Forge for the first time.
2. SSH in once (Forge → server → **SSH** button gives you the command) to generate the app key, since it must be generated exactly once and then never change:
   ```bash
   cd /home/forge/copamsterdam.nl
   php artisan key:generate
   php artisan db:seed --class=RolesAndPermissionsSeeder
   php artisan db:seed --class=ChurchSeeder
   ```
   `RolesAndPermissionsSeeder` and `ChurchSeeder` are both idempotent — safe to re-run, they won't duplicate data. `ChurchSeeder` prints the generated admin password **once**; copy it into your password manager before you clear the terminal. If the account already exists it leaves the password untouched rather than resetting it.
3. **Never run `DemoSeeder` here.** It creates fake members, events, and announcements. `ChurchSeeder` also skips its sample-member account when `APP_ENV=production`, so production gets exactly one real account.
4. Log in at `https://copamsterdam.nl/login` (once DNS/SSL are live — Steps 8–9) with that admin email and the generated password.

---

## STEP 8 — Point the domain at the server (DNS)

Every domain has DNS records that tell the internet which server answers for it. You'll edit these at **TransIP → your domain → DNS**.

- **`A` record** — maps a hostname directly to an IPv4 address. This is the core record.
- **`@`** — means "the bare/root domain," i.e. `copamsterdam.nl` itself (no subdomain prefix).
- **`www`** — the `www.copamsterdam.nl` subdomain, a separate hostname from the bare domain even though people expect them to behave the same.
- **`CNAME`** — points a hostname to *another hostname* (not an IP) — used for `www` sometimes instead of a second `A` record.
- **Nameservers** — which DNS provider is authoritative for the domain. You're keeping TransIP's own nameservers and editing records directly in their panel (simplest option — no need to switch to Cloudflare or anywhere else unless you want extra features like DDoS protection later).

**Records to create for `copamsterdam.nl`:**

| Type | Host | Value | TTL |
|---|---|---|---|
| A | `@` | *your Hetzner server's IPv4 address (from Step 5)* | 300 |
| A | `www` | *same IPv4 address* | 300 |

**Records to create for `copamsterdam.org`** (once you also add it as a site alias in Forge, or simply as a redirect-only domain — see Step 16):

| Type | Host | Value | TTL |
|---|---|---|---|
| A | `@` | *same IPv4 address* | 300 |
| A | `www` | *same IPv4 address* | 300 |

Back in Forge, add `www.copamsterdam.nl` and `copamsterdam.org` / `www.copamsterdam.org` as **aliases** on the same site (Site → Meta → Aliases), so Nginx knows to answer for all four hostnames.

**DNS propagation:** changes are not instant. TTL 300 means resolvers should refresh within 5 minutes, but full global propagation can take up to 24–48 hours in rare cases (mostly ISP-level caching). Check with:

```bash
nslookup copamsterdam.nl
```

Don't panic if it's not resolving everywhere in the first hour.

---

## STEP 9 — HTTPS / SSL

Forge automates this — no manual Certbot commands needed (unlike the raw-VPS path in `DEPLOYMENT.md`).

1. Once DNS has propagated (Step 8), go to the site in Forge → **SSL** tab.
2. Choose **Let's Encrypt**, add domains `copamsterdam.nl`, `www.copamsterdam.nl`, `copamsterdam.org`, `www.copamsterdam.org`.
3. Click **Obtain Certificate**. Forge issues the cert, installs it in Nginx, and reloads — usually under a minute once DNS is correctly pointed.
4. Forge auto-renews Let's Encrypt certificates on a schedule — nothing further required from you.
5. In the site's **Nginx** settings (or via Forge's redirect tool), force **HTTP → HTTPS**: Forge does this automatically once SSL is active, but verify by visiting `http://copamsterdam.nl` and confirming it redirects to `https://`.

**Verify SSL is working:**
```bash
curl -I https://copamsterdam.nl
# Expect: HTTP/2 200, and no certificate warnings
```
Or just visit the site in a browser and check for the padlock icon with no warnings.

---

## STEP 10 — Laravel production configuration values

Already set in Step 7.3, summarized here as the checklist your request asked for:

| Setting | Value | Why |
|---|---|---|
| `APP_ENV` | `production` | Disables debug-only behaviors, enables prod optimizations |
| `APP_DEBUG` | `false` | **Critical.** `true` leaks stack traces, file paths, env values to any visitor who hits an error |
| `APP_URL` | `https://copamsterdam.nl` | Used to generate absolute URLs (emails, sitemap, OG tags) |
| `DB_CONNECTION` | `mysql` | SQLite (dev default) is not supported in production per `DEPLOYMENT.md` |
| `CACHE_STORE` / `SESSION_DRIVER` | `redis` | Avoids DB-table overhead, required for scaling later |
| `QUEUE_CONNECTION` | `redis` | Dev uses `sync` (inline, no worker); prod needs a real queue + worker (Step 11) |
| `LOG_LEVEL` | `warning` | Dev default `debug` is far too verbose/slow for production logs |

---

## STEP 11 — Queues, Reverb, and scheduled tasks

Your app has three background processes that must run continuously in production. Forge manages all of them as **Daemons** (Site → **Daemons** tab, or Server-level → **Daemons**), which is Forge's equivalent of hand-writing Supervisor `.conf` files.

**1. Queue worker** (processes YouTube sync jobs, notification delivery, broadcasts):
```
php /home/forge/copamsterdam.nl/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
```
Set **Processes: 2**.

**2. Reverb WebSocket server** (real-time notifications):
```
php /home/forge/copamsterdam.nl/artisan reverb:start --host=0.0.0.0 --port=8080 --no-interaction
```
Set **Processes: 1**.

For Reverb to be reachable at `wss://copamsterdam.nl`, add this to the site's **Nginx configuration** (Forge → site → **Files** → **Edit Nginx Configuration**), inside the existing `server { listen 443 ssl ... }` block:

```nginx
location /app/ {
    proxy_pass http://127.0.0.1:8080;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "Upgrade";
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
}
```

**3. Laravel scheduler** (drives the hourly YouTube channel auto-sync defined in `routes/console.php`). This one is a **cron entry**, not a daemon — Forge → server → **Scheduler** tab has a toggle that adds exactly this line for you:
```
* * * * * php /home/forge/copamsterdam.nl/artisan schedule:run >> /dev/null 2>&1
```

After every deploy, Forge's deploy script (Step 7.2) already includes `queue:restart`, which gracefully cycles the queue workers to pick up new code. Reverb needs a manual restart from the Daemons tab after deploys that touch broadcasting code — click **Restart** on that daemon.

---

## STEP 12 — Email

Your app sends: password reset links, RSVP confirmations, task/announcement/event notifications, and contact-form/prayer-request confirmations. `MAIL_MAILER=log` (dev default) writes emails to a log file instead of sending them — **this must change for production.**

> **Correction (2026-09-30).** An earlier version of this step recommended Mailgun.
> That was written from the generic Laravel docs, not from this codebase. **Mailgun
> is not usable here without extra work**: Laravel removed it from the default mail
> config, so there is no `mailgun` block in `config/mail.php` and no transport
> package installed. Setting `MAIL_MAILER=mailgun` fails.
>
> The mailers actually defined in `config/mail.php` are `smtp`, `ses`, `postmark`,
> `resend`, `sendmail`, `log`, `array`, `failover` and `roundrobin` — and of those,
> only **`smtp`** works with no additional package, because it is part of
> `symfony/mailer` which is already installed.

**Recommended: SMTP.** It needs no new packages and no code change, and every
provider (Postmark, Resend, Brevo, SMTP2GO, Mailgun, Fastmail …) issues SMTP
credentials — so the choice of provider stays reversible.

1. Create an account with a transactional email provider and verify
   `copamsterdam.nl` as a sending domain. The provider will give you an SMTP
   host, port, username and password, plus DNS records.
2. Add the provider's DNS records at **TransIP → copamsterdam.nl → DNS**:

| Type | Host | Purpose |
|---|---|---|
| TXT | `@` | **SPF** — must include the provider's mechanism (see warning below) |
| TXT | provider-specified, e.g. `s1._domainkey` | **DKIM** — signs outgoing mail |
| TXT | `_dmarc` | **DMARC** — start at `p=none`, tighten later |

> **Important — the existing SPF record blocks all sending.** `copamsterdam.nl`
> currently publishes `v=spf1 ~all`, which authorises *no* sender. It must be
> replaced (not duplicated — a domain may publish only one SPF record) with one
> that includes the provider, e.g. `v=spf1 include:spf.example-provider.com ~all`.

3. Put the credentials in `/var/www/copam/.env`, then re-cache config (see below).
4. **Test delivery** over SSH — see the verification command in this guide's
   companion notes, which reports the actual transport error rather than failing
   silently.

---

## STEP 13 — Storage, files, and backups

**Current setup:** `FILESYSTEM_DISK=local` — uploaded logos, sermon files, member documents, etc. live on the server's disk at `storage/app/public`, symlinked to `public/storage`. This is fine at your scale (single server, no horizontal scaling planned), but it means **the server itself is the single point of truth for uploaded files** — if it's destroyed, uploads are gone unless backed up separately from the database.

**Backup strategy (answering "if the server dies tomorrow, how do I recover?"):**

1. **Database backups — Forge's built-in backup tool** (Server → **Backups** tab): configure daily backups to an external destination — Forge supports S3-compatible storage directly. Use **Hetzner Object Storage** (their S3-compatible service, cheap, same provider/region) or Backblaze B2. Retention: keep at minimum 7 daily + 4 weekly.
2. **File backups**: same Hetzner Object Storage bucket, synced nightly via a scheduled command. Add to `routes/console.php`'s schedule (or a Forge scheduled job):
   ```bash
   tar -czf - -C /home/forge/copamsterdam.nl/storage/app/public . | \
     aws s3 cp - s3://your-backup-bucket/storage-$(date +%F).tar.gz --endpoint-url https://your-hetzner-endpoint
   ```
3. **Full-server safety net**: enable **Hetzner Cloud automatic snapshots** (weekly) on the server itself in the Hetzner console — cheap insurance that restores the entire box, not just app data.

**To restore from scratch:** provision a new server (Step 5), redeploy the app via Forge (Step 7, code comes from GitHub — never lost), restore the latest DB backup (`mysql < backup.sql`), restore the storage/ files from the object storage bucket, run `php artisan storage:link`, update DNS A records to the new IP (Step 8). Practice this once *before* you need it for real.

---

## STEP 14 — SEO

Already built in the codebase and verified present: `/sitemap.xml` and `/robots.txt` routes (`SitemapController`, `RobotsController`), plus the 23-section Settings hub includes an SEO tab (meta/OG per your architecture notes). Checklist to verify once live:

- [ ] Visit `https://copamsterdam.nl/sitemap.xml` — confirm it lists real pages (home, about, events, sermons, ministries, announcements)
- [ ] Visit `https://copamsterdam.nl/robots.txt` — confirm it references the sitemap and doesn't accidentally block everything
- [ ] Dashboard → Settings → SEO — fill in page titles/meta descriptions specific to "Church of Pentecost Amsterdam", not the Ghana-tenant placeholder text
- [ ] Open Graph image set (used when the site is shared on WhatsApp/Facebook/etc. — important, church links get shared a lot)
- [ ] Add **LocalBusiness / Church structured data** (JSON-LD) if not already present — helps Google Maps/local search show service times and address correctly. If missing, this is a good small follow-up task.
- [ ] Canonical URLs point to `https://copamsterdam.nl/...` (not `www`, not `.org`) once the redirect structure in Step 16 is live
- [ ] Custom 404 page exists and is on-brand (check `resources/views/errors/`)
- [ ] Submit the sitemap to Google Search Console once live (`search.google.com/search-console`, add property, verify via DNS TXT record at TransIP, submit `sitemap.xml`)

---

## STEP 15 — Performance, accessibility, mobile

**Performance** — run Lighthouse (Chrome DevTools → Lighthouse tab) against the live homepage, events page, and sermons page once deployed. Watch for:
- Largest Contentful Paint driven by hero images — confirm they're compressed/served at reasonable resolution (check any gallery/hero images uploaded through the admin, not just the SVG placeholders from `DemoSeeder`)
- `npm run build` output (already checked today) is fine — main JS bundle is ~315KB / 109KB gzipped, dashboard chunk ~118KB/35KB gzipped — reasonable for this feature set, no action needed
- Static asset caching is already configured in the Nginx template in `DEPLOYMENT.md` (`expires 1y` on JS/CSS/images) — confirm Forge's generated Nginx config includes it, or add manually

**Accessibility (WCAG 2.2 AA)** — spot-check with the browser's accessibility tree and keyboard-only navigation on:
- [ ] Homepage → Tab through nav, ensure visible focus states
- [ ] Events RSVP flow — form labels present, error messages announced
- [ ] Contact/prayer forms — every input has an associated `<label>`
- [ ] Color contrast — the brand palette (`#5AA9E6` light blue especially) can fail AA contrast on white backgrounds if used for body text; fine for accents/buttons with white text, check contrast if used for small text
- [ ] `prefers-reduced-motion` — already honored in the Youth Ministry page per your notes; confirm the same holds on other animated sections

**Mobile** — test on real devices or Chrome DevTools device emulation at minimum:
- [ ] iPhone-size viewport (390×844) and Android (360×800): nav menu opens/closes, Events/Sermons pages don't horizontal-scroll, RSVP buttons are tappable (44px target size)
- [ ] Tablet (768–1024px): dashboard sidebar behavior
- [ ] Admin dashboard on mobile — at minimum confirm it's usable for a coordinator checking things from their phone, even if desktop is the primary admin experience

---

## STEP 16 — Final domain structure

**Canonical:** `https://copamsterdam.nl`

- `https://www.copamsterdam.nl` → 301 redirect → `https://copamsterdam.nl` (non-www canonical is simpler and matches your `APP_URL`)
- `https://copamsterdam.org` and `https://www.copamsterdam.org` → 301 redirect → `https://copamsterdam.nl`

This is the right setup: one canonical URL avoids duplicate-content SEO penalties and keeps analytics/session cookies simple, while still owning `.org` defensively so nobody else can use it to impersonate the church.

**How to implement the redirects in Forge:** on the `.org` site (add it as its own Forge site, or as a redirect-only entry), use Forge's **Redirects** tool (Site → Redirects) or add to the Nginx config:

```nginx
server {
    listen 443 ssl;
    server_name copamsterdam.org www.copamsterdam.org;
    # SSL cert config (Step 9 already issued one covering these hostnames)
    return 301 https://copamsterdam.nl$request_uri;
}
```

Same pattern for `www.copamsterdam.nl → copamsterdam.nl`, on the primary site's Nginx config, ahead of the main `location /` block.

---

## STEP 17 — Go-live checklist

- [ ] `copamsterdam.nl` registered (Step 4)
- [ ] `copamsterdam.org` registered (Step 4)
- [ ] Hetzner + Forge accounts created, server provisioned (Steps 3, 5)
- [ ] Site created in Forge, GitHub repo connected (Step 6, 7.1)
- [ ] Production `.env` fully filled in — no placeholder values, `APP_DEBUG=false` (Step 7.3, 10)
- [ ] `ChurchSeeder`'s `TODO:` placeholders replaced with real assembly address/phone/admin name
- [ ] Database created, migrations run with zero errors (Step 7.4)
- [ ] `RolesAndPermissionsSeeder` + `ChurchSeeder` run — **not** `DemoSeeder`
- [ ] `storage:link` run, uploads tested (logo upload, one sermon file)
- [ ] Frontend built (`npm run build` as part of deploy script)
- [ ] Nginx serving the site (Forge-managed)
- [ ] DNS A records point at the server for both domains + `www` (Step 8)
- [ ] SSL certificates issued and auto-renewing for all four hostnames (Step 9)
- [ ] HTTP → HTTPS redirect confirmed
- [ ] `www` → apex and `.org` → `.nl` redirects live (Step 16)
- [ ] SMTP provider configured, existing `v=spf1 ~all` replaced, DKIM verified, test email received (Step 12)
- [ ] Scheduler cron active, queue worker daemon running, Reverb daemon running (Step 11)
- [ ] Backups configured and one manual backup verified to actually restore (Step 13)
- [ ] Sitemap/robots verified live, Search Console property added (Step 14)
- [ ] Mobile nav/RSVP/forms tested on a real phone (Step 15)
- [ ] Admin logs in, changes the seeded default password
- [ ] Public site walked end-to-end: home → events → RSVP → sermons → contact form → announcement visible after being published in dashboard
- [ ] `https://copamsterdam.nl` loads correctly for someone outside your network (ask a friend to check, don't rely only on your own browser which may have cached DNS)

---

## STEP 18 — After launch

**Daily**
- Skim `storage/logs/laravel.log` for new errors (or wire up a proper error tracker like Sentry — recommended first post-launch improvement)
- Confirm the site loads (a free uptime monitor like UptimeRobot pinging `https://copamsterdam.nl` every 5 min is a five-minute setup and catches outages before members complain)

**Weekly**
- Check `php artisan queue:failed` for stuck jobs (mainly YouTube sync)
- Skim Forge's backup log — confirm the nightly backup actually ran
- Review any contact-form/prayer-request submissions

**Monthly**
- `composer outdated` / `npm outdated` — review for security patches, apply non-breaking updates
- Confirm SSL certificate is auto-renewing (`sudo certbot certificates` equivalent is visible in Forge's SSL tab — check expiry date is always >30 days out)
- Review Search Console for crawl errors, and basic analytics if wired up

**Quarterly**
- Full restore drill from backups on a throwaway server — confirms your disaster recovery plan (Step 13) actually works, not just that it's configured
- Review Spatie roles/permissions for any accounts that should be deactivated (departed volunteers, etc.)
- Re-check Laravel/Vue major version upgrade paths — don't let the framework drift too far behind

---

## Troubleshooting

For anything server-side that misbehaves after deploy (class not found, queue not processing, uploads 404ing, WebSocket not connecting), `DEPLOYMENT.md` section 12 has the exact diagnostic commands — they apply identically whether Forge or a hand-rolled VPS is running the box underneath.
