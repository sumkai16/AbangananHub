# Hostinger VPS Deployment Runbook

Target: Ubuntu 22.04 VPS, Nginx, PHP 8.2-FPM, MySQL, Supervisor-managed Reverb, Certbot SSL.

This is the deploy procedure for AbangananHub as it exists today (Aug 2026). It assumes a fresh VPS
and a domain pointed at it. Substitute `abangananhub.com` and `/var/www/abangananhub` throughout.

**Read `§5 Production .env` and `§6 Build order` before running anything.** The single most likely
way to ship a broken site here is building assets before the production env values are in place —
the Reverb client host is compiled into the JS bundle, so a wrong value cannot be fixed by editing
`.env` afterwards.

---

## 1. Why a VPS (and not a PaaS)

Three hard requirements rule out shared hosting and ephemeral-filesystem platforms:

- **Persistent private disk.** `landlord_verifications.government_id` and `property_documents.file_path`
  write to `storage/app/private` and are served only through policy-gated routes — deliberately not
  Cloudinary (ARCHITECTURE.md § File Upload, SCHEMA.md § property_documents). A platform that resets
  its filesystem on redeploy loses government IDs.
- **A long-running daemon.** Reverb must stay up for real-time chat.
- **Real cron.** `reservations:process-move-in-deadlines` moves money unattended nightly.

---

## 2. Server provisioning

```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y nginx mysql-server supervisor git unzip curl \
  php8.2-fpm php8.2-mysql php8.2-mbstring php8.2-xml php8.2-curl \
  php8.2-zip php8.2-gd php8.2-bcmath php8.2-intl

# Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Node 20 (for the Vite build)
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs
```

Firewall — note that **8080 is deliberately not opened**. Reverb binds to loopback and is reached
only through Nginx on 443 (see §7):

```bash
sudo ufw allow OpenSSH
sudo ufw allow 'Nginx Full'
sudo ufw enable
```

### PHP tuning

In `/etc/php/8.2/fpm/php.ini`:

```ini
memory_limit = 256M
upload_max_filesize = 12M    ; unit photos + ID captures
post_max_size = 64M          ; unit create posts up to 10 photos at once
max_execution_time = 60

opcache.enable = 1
opcache.validate_timestamps = 0   ; production only — requires an FPM reload to pick up code changes
```

`post_max_size` matters: the unit-create form submits an aggregated `photos[]` input carrying up to
10 images (≥3 live camera captures). A low value truncates the POST and the alignment validation
fails with a confusing error.

```bash
sudo systemctl restart php8.2-fpm
```

---

## 3. Database

```bash
sudo mysql_secure_installation
sudo mysql
```

```sql
CREATE DATABASE abanganan_hub CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'abanganan'@'localhost' IDENTIFIED BY 'USE-A-REAL-GENERATED-PASSWORD';
GRANT ALL PRIVILEGES ON abanganan_hub.* TO 'abanganan'@'localhost';
FLUSH PRIVILEGES;
```

Do not deploy with `root` / empty password, which is what local dev uses.

---

## 4. Code

```bash
sudo mkdir -p /var/www/abangananhub
sudo chown -R $USER:www-data /var/www/abangananhub
cd /var/www/abangananhub
git clone <repo-url> .
git checkout main

composer install --no-dev --optimize-autoloader
npm ci
```

Do **not** run `php artisan migrate` or `npm run build` yet — both need the production `.env`.

---

## 5. Production `.env`

Copy the local `.env` up as a starting point (it already has every integration configured), then
apply the deltas below. Full template:

```dotenv
APP_NAME=AbangananHub
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:...                       # keep the existing key, or php artisan key:generate
APP_URL=https://abangananhub.com

APP_LOCALE=en
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=en_US
APP_MAINTENANCE_DRIVER=file
BCRYPT_ROUNDS=12

LOG_CHANNEL=stack
LOG_STACK=daily
LOG_LEVEL=error

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=abanganan_hub
DB_USERNAME=abanganan
DB_PASSWORD=USE-A-REAL-GENERATED-PASSWORD

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=null
SESSION_SECURE_COOKIE=true

BROADCAST_CONNECTION=reverb
FILESYSTEM_DISK=local
QUEUE_CONNECTION=database
CACHE_STORE=database

# --- Reverb: three distinct roles, do not collapse them ---
# 1. What the daemon binds to (loopback; Nginx is the only thing that reaches it)
REVERB_SERVER_HOST=127.0.0.1
REVERB_SERVER_PORT=8080

# 2. How the PHP app publishes events to the daemon (internal, no TLS)
#    Copy the three app credentials from the local .env — do not invent new
#    ones, the client bundle is built against REVERB_APP_KEY.
REVERB_APP_ID=
REVERB_APP_KEY=
REVERB_APP_SECRET=
REVERB_HOST=127.0.0.1
REVERB_PORT=8080
REVERB_SCHEME=http

# 3. What the BROWSER connects to — public domain, through Nginx on 443
VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST=abangananhub.com
VITE_REVERB_PORT=443
VITE_REVERB_SCHEME=https

MAIL_MAILER=smtp
MAIL_HOST=...
MAIL_PORT=587
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="noreply@abangananhub.com"
MAIL_FROM_NAME="AbangananHub"

CLOUDINARY_CLOUD_NAME=
CLOUDINARY_API_KEY=
CLOUDINARY_API_SECRET=
CLOUDINARY_URL=

PAYMONGO_SECRET_KEY=
PAYMONGO_PUBLIC_KEY=
PAYMONGO_WEBHOOK_SECRET=

GOOGLE_VISION_API_KEY=
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI="${APP_URL}/auth/google/callback"

FACEBOOK_CLIENT_ID=
FACEBOOK_CLIENT_SECRET=
FACEBOOK_REDIRECT_URI="${APP_URL}/auth/facebook/callback"
```

> **Never fill these in inside this file.** `plans/` is tracked by git; `.env` is not. Copy the real
> values from the local `.env` straight onto the server (`scp`, or paste over SSH) and leave every
> secret blank here. The same applies to any future doc in the repo.

### The Reverb split is the critical part

Local dev uses `VITE_REVERB_HOST="${REVERB_HOST}"` — interpolating the two together. **That
interpolation must be broken in production.** They mean different things:

| Variable | Who reads it | Production value |
|---|---|---|
| `REVERB_SERVER_HOST` / `_PORT` | the Reverb daemon, to bind | `127.0.0.1` / `8080` |
| `REVERB_HOST` / `_PORT` / `_SCHEME` | PHP app → daemon (internal) | `127.0.0.1` / `8080` / `http` |
| `VITE_REVERB_HOST` / `_PORT` / `_SCHEME` | the browser → Nginx → daemon | `abangananhub.com` / `443` / `https` |

`resources/js/echo.js` sets `wssPort: import.meta.env.VITE_REVERB_PORT ?? 443`. Leaving
`VITE_REVERB_PORT=8080` makes every browser attempt `wss://abangananhub.com:8080`, which the
firewall drops — chat silently never connects, with nothing in the Laravel log.

`SESSION_SECURE_COOKIE=true` is added because the site is HTTPS-only; without it the session cookie
is offered over plain HTTP too.

Note `APP_URL` cascades into both OAuth redirect URIs automatically — but see §10, the provider
consoles still need the new callback registered by hand.

---

## 6. Build order (env first, then assets)

The order matters. `npm run build` bakes `VITE_*` values into `public/build/*.js` as literals.

```bash
cd /var/www/abangananhub

# 1. .env must already hold production values (§5)
php artisan key:generate --force      # only if not reusing the existing APP_KEY

# 2. Schema
php artisan migrate --force

# 3. Assets — AFTER the env is correct
npm run build

# 4. Caches (these read .env, so they come last)
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 5. Public symlink for the local (public) disk
php artisan storage:link
```

Anything that changes a `VITE_*` value later requires a **rebuild**, not just a `config:clear`.

### Permissions

```bash
sudo chown -R www-data:www-data /var/www/abangananhub
sudo find /var/www/abangananhub -type f -exec chmod 644 {} \;
sudo find /var/www/abangananhub -type d -exec chmod 755 {} \;
sudo chmod -R 775 /var/www/abangananhub/storage /var/www/abangananhub/bootstrap/cache
```

`storage/app/private/` holds government IDs and property documents. It sits outside `public/` and
must stay that way — it is reachable only through the policy-gated controller routes.

---

## 7. Nginx

`/etc/nginx/sites-available/abangananhub`:

```nginx
server {
    listen 80;
    server_name abangananhub.com www.abangananhub.com;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name abangananhub.com www.abangananhub.com;
    root /var/www/abangananhub/public;

    index index.php;
    charset utf-8;

    # certbot fills these in (§8)
    # ssl_certificate ...
    # ssl_certificate_key ...

    client_max_body_size 64M;   # must match post_max_size — unit photo batches

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    # --- Reverb WebSocket proxy ---
    # The browser connects to wss://abangananhub.com/app/... on 443;
    # this forwards to the daemon on loopback:8080.
    location /app/ {
        proxy_pass http://127.0.0.1:8080;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_read_timeout 3600s;
        proxy_send_timeout 3600s;
    }

    # Reverb's event-publishing endpoint (PHP app -> daemon goes direct to
    # 127.0.0.1, but this keeps the HTTP API reachable if ever needed)
    location /apps/ {
        proxy_pass http://127.0.0.1:8080;
        proxy_http_version 1.1;
        proxy_set_header Host $host;
        proxy_set_header X-Forwarded-Proto $scheme;
    }

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 60;
    }

    location ~ /\.(?!well-known).* { deny all; }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    access_log /var/log/nginx/abangananhub-access.log;
    error_log  /var/log/nginx/abangananhub-error.log;
}
```

```bash
sudo ln -s /etc/nginx/sites-available/abangananhub /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t && sudo systemctl reload nginx
```

The `proxy_read_timeout 3600s` on `/app/` is required — the default 60s kills idle WebSocket
connections, so a chat page left open for a minute silently stops receiving messages.

---

## 8. SSL

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d abangananhub.com -d www.abangananhub.com
sudo systemctl status certbot.timer     # auto-renewal
```

HTTPS is not optional here beyond the usual reasons: **`getUserMedia` requires a secure context.**
Landlord ID verification and the ≥3 live camera captures on unit creation will not work at all over
plain HTTP.

---

## 9. Supervisor (Reverb daemon)

`/etc/supervisor/conf.d/abangananhub-reverb.conf`:

```ini
[program:abangananhub-reverb]
process_name=%(program_name)s
command=php /var/www/abangananhub/artisan reverb:start
directory=/var/www/abangananhub
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/www/abangananhub/storage/logs/reverb.log
stopwaitsecs=10
```

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start abangananhub-reverb
sudo supervisorctl status
```

**No queue worker is configured, deliberately.** Broadcast events are `ShouldBroadcastNow`
(synchronous) and no Mailable implements `ShouldQueue` — see ARCHITECTURE.md § Known Tradeoffs.
`QUEUE_CONNECTION=database` is set but nothing dispatches to it. If a future change queues anything,
a second Supervisor program running `queue:work` becomes mandatory, and its absence will fail
silently.

---

## 10. Scheduler cron

```bash
sudo crontab -u www-data -e
```

```cron
* * * * * cd /var/www/abangananhub && php artisan schedule:run >> /dev/null 2>&1
```

This one line drives all three scheduled commands (`routes/console.php`):

| Time | Command | Consequence if it never runs |
|---|---|---|
| 08:00 | `reservations:process-rent-reminders` | no rent reminders reach landlords or tenants |
| 23:00 | `reservations:process-move-in-deadlines` | **escrow clocks stop** — deposits are never escalated or auto-released |
| 23:55 | `occupancy:snapshot` | occupancy history gaps permanently (it cannot be reconstructed later) |

Confirm the server clock is Philippine time, or all three fire at the wrong local hour:

```bash
sudo timedatectl set-timezone Asia/Manila
```

---

## 11. Third-party re-registration

Changing `APP_URL` updates the app's own redirect URIs, but each provider console must be told
separately. All four of these fail *at the provider*, so the Laravel log stays clean.

- **Google Cloud Console** → OAuth 2.0 Client → Authorized redirect URIs → add
  `https://abangananhub.com/auth/google/callback`.
- **Facebook App** → Facebook Login → Valid OAuth Redirect URIs → add
  `https://abangananhub.com/auth/facebook/callback`.
- **Both apps are still in Testing/Development mode** (ARCHITECTURE.md § Social Login). Only
  explicitly-added test users can log in until they are published. Decide before the defense whether
  social login is demoed with a pre-added test account or published properly.
- **PayMongo** → Webhooks → point at the live webhook URL over HTTPS. This is a genuine behaviour
  change: PayMongo cannot reach `localhost`, so in local dev the webhook has *never* fired and the
  poll-fallback in `ReconcilesPaymongoCheckout` has been the only path that ever settles a payment.
  On the VPS the webhook path goes live for the first time. Run one real sandbox payment end-to-end
  after deploying and confirm the payment reaches `Held` and the conversation stepper moves to Paid.

### Mail

Local uses **Mailtrap sandbox, which captures mail and never delivers it.** Password-reset and
email-verification links will not reach a real inbox on the live site. If any live flow needs to
actually deliver (a panel member receiving a reset link), swap `MAIL_*` for a real sender before the
defense. If the demo only shows the Mailtrap inbox, leaving it is fine — but decide deliberately.

---

## 12. Post-deploy smoke checklist

Ordered so a failure points at the layer that caused it.

- [ ] `https://abangananhub.com` loads, padlock valid, redirects from `http://`
- [ ] Styling is present (if unstyled → `npm run build` didn't run, or `public/build` is missing)
- [ ] Register a tenant → lands on `/properties` (`User::homeRoute()`)
- [ ] Browse: search, filters, map tiles, a property detail page
- [ ] **Chat**: two accounts, two browsers, message delivers in <2s with no reload.
      If not: `sudo supervisorctl status`, then browser devtools → Network → WS. A failed
      connection to `:8080` means `VITE_REVERB_PORT` was wrong at build time — rebuild, don't
      just edit `.env`.
- [ ] Landlord verification wizard: camera opens (fails on non-HTTPS), liveness runs, submit
- [ ] Admin approves the verification → Landlord role granted → `rental_businesses` row created
- [ ] Create a property through the wizard: pin drops on the map, Cloudinary photos upload
- [ ] Create a unit with ≥3 live camera captures
- [ ] Upload a property document → admin verifies it → "Verified Property" badge appears
- [ ] Document preview renders for **both** landlord and admin (separate routes — ARCHITECTURE.md
      Aug 21 2026)
- [ ] One full sandbox payment: checkout → webhook → `Held` → stepper moves to Paid live
- [ ] `sudo -u www-data php artisan schedule:list` shows all three commands
- [ ] `tail -f storage/logs/laravel.log` is clean
- [ ] A deliberate 404 shows the branded error page

---

## 13. Redeploy procedure

```bash
cd /var/www/abangananhub
php artisan down

git pull origin main
composer install --no-dev --optimize-autoloader
php artisan migrate --force
npm ci && npm run build

php artisan config:cache
php artisan route:cache
php artisan view:cache

sudo supervisorctl restart abangananhub-reverb   # picks up changed broadcast code
sudo systemctl reload php8.2-fpm                 # required: opcache.validate_timestamps=0

php artisan up
```

`supervisorctl restart` is not optional — the Reverb daemon holds the old code in memory otherwise.
Same for the FPM reload, since opcache is set not to check timestamps.

---

## 14. Gotchas specific to this codebase

- **`REVERB_HOST=127.0.0.1` is right for the server and wrong for the browser.** The local-dev rule
  "IPv4 everywhere, never `localhost`" (RULES.md § Performance) is a *Windows* fix and does not
  transfer to the client half in production.
- **`VITE_*` changes need a rebuild.** They are compiled literals, not runtime lookups.
- **`php artisan route:cache` is safe in production and banned in dev** (RULES.md). Don't carry the
  dev habit of `route:clear` into the deploy script — cache it.
- **`Model::preventLazyLoading` is disabled in production** (`! app()->isProduction()`), so an N+1
  that would throw locally will merely be slow live. Do not treat a clean prod page as proof.
- **`APP_DEBUG=false` is load-bearing.** With it true, a 500 renders a stack trace containing the
  Cloudinary secret, the PayMongo key and the DB password.
- **Abandoned Draft properties accumulate** with no cleanup job (ARCHITECTURE.md § Known Tradeoffs).
  Inert, but expect them in production data.
- **`audit_logs` grows unbounded** — no pruning. Fine at capstone volume.
- **`.env` is gitignored and has never been committed** — verified. Keep it that way; copy it to the
  server over `scp`, never through the repo.

---

## 15. Deferred (not blocking the defense)

- Real mail sender if live delivery is ever needed
- Publishing the Google/Facebook OAuth apps out of Testing mode
- Redis for cache/session (DB-backed is fine at this volume)
- A queue worker (only if something starts implementing `ShouldQueue`)
- Off-server backups — at minimum a nightly `mysqldump` plus `storage/app/private`, which holds the
  only copy of every government ID and property document
