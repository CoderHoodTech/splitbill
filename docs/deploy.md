# SplitBill — Production Deploy Runbook

> Target: a small Linux VPS (~Rp200k/mo, 1–2 vCPU, 1–2 GB RAM) running both Laravel
> and the Python OCR microservice on the same host. SQLite to start; MariaDB later
> with only `.env` changes.
>
> The local architecture is described in the [README architecture section](../README.md#architecture).
> The runbook below mirrors that shape; only the process managers change.

## 1. Prerequisites on the VPS

Debian/Ubuntu LTS is assumed. Versions match those in [composer.json](../composer.json)
and [CLAUDE.md](../CLAUDE.md).

```bash
# System
sudo apt update && sudo apt -y upgrade
sudo apt -y install software-properties-common curl unzip git

# PHP 8.3+ (8.4 preferred, matches dev)
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update
sudo apt -y install \
    php8.4-fpm php8.4-cli php8.4-common \
    php8.4-mbstring php8.4-xml php8.4-curl php8.4-zip php8.4-bcmath \
    php8.4-sqlite3 php8.4-mysql

# Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Node.js LTS (for Vite build)
curl -fsSL https://deb.nodesource.com/setup_lts.x | sudo -E bash -
sudo apt -y install nodejs

# OCR runtime — Tesseract 5 + Indonesian language pack
sudo apt -y install tesseract-ocr tesseract-ocr-ind
tesseract --list-langs   # must list `ind`

# Python 3.11+ for the OCR microservice
sudo apt -y install python3 python3-venv python3-pip

# Web server (pick one)
sudo apt -y install caddy        # OR: nginx
```

## 2. Lay down the app

```bash
sudo mkdir -p /var/www/splitbill
sudo chown -R "$USER":www-data /var/www/splitbill
cd /var/www/splitbill
git clone <repo-url> .
```

## 3. Production `.env`

Copy `.env.example` and harden it. The keys that MUST change:

| Key | Value | Notes |
|---|---|---|
| `APP_ENV` | `production` | Activates the stronger password rules in `AppServiceProvider`. |
| `APP_DEBUG` | `false` | Never expose stack traces in prod. |
| `APP_KEY` | run `php artisan key:generate` | One time only. |
| `APP_URL` | `https://splitbill.example.com` | Used by URL generation and emails. |
| `DB_CONNECTION` | `sqlite` (default) | Keep until traffic outgrows it. |
| `DB_DATABASE` | absolute path | e.g. `/var/www/splitbill/storage/app/database.sqlite` |
| `OCR_BASE_URL` | `http://127.0.0.1:8001` | Loopback only — see §6. |
| `OCR_TIMEOUT` | `20` | Seconds Laravel will wait for the scan. |
| `SESSION_DRIVER` | `database` or `file` | `array` is fine in dev but not prod. |
| `QUEUE_CONNECTION` | `database` | See §7. |
| `CACHE_STORE` | `database` or `file` | Cheapest on a small VPS. |
| Mail (`MAIL_*`) | per provider | Needed for password reset to actually send. |

The `.env` file is gitignored ([.gitignore:12](../.gitignore#L12)). Never commit it.

## 4. Web server + PHP-FPM

### Option A — Caddy (simplest, auto-HTTPS)

`/etc/caddy/Caddyfile`:

```caddy
splitbill.example.com {
    root * /var/www/splitbill/public
    php_fastcgi unix//run/php/php8.4-fpm.sock
    file_server
    encode zstd gzip
    header {
        X-Content-Type-Options "nosniff"
        Referrer-Policy "strict-origin-when-cross-origin"
        Permissions-Policy "interest-cohort=()"
    }
}
```

```bash
sudo systemctl reload caddy
```

### Option B — Nginx

Standard Laravel nginx config pointing `root` at `/var/www/splitbill/public` and the
PHP-FPM pool at `unix:/run/php/php8.4-fpm.sock`. Add `client_max_body_size 6M;` so the
5 MB receipt upload limit isn't blocked.

Either way the app itself stays the same — only how requests reach PHP-FPM changes.

## 5. Build & migrate

```bash
cd /var/www/splitbill
composer install --no-dev --optimize-autoloader
npm ci && npm run build
touch storage/app/database.sqlite     # if using SQLite
php artisan migrate --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Permissions (run once, then on every deploy):

```bash
sudo chown -R www-data:www-data storage bootstrap/cache
sudo find storage bootstrap/cache -type d -exec chmod 775 {} \;
sudo find storage bootstrap/cache -type f -exec chmod 664 {} \;
```

## 6. OCR microservice as a systemd unit

The Python service binds to **127.0.0.1:8001 only** — never publicly. See the
[OCR microservice README](../ocr-service/README.md) for the engine, the stable
`/ocr` contract, and how to swap Tesseract for RapidOCR.

```bash
cd /var/www/splitbill/ocr-service
python3 -m venv .venv
.venv/bin/pip install -r requirements.txt
```

`/etc/systemd/system/splitbill-ocr.service`:

```ini
[Unit]
Description=SplitBill OCR microservice (Tesseract / RapidOCR)
After=network.target

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=/var/www/splitbill/ocr-service
ExecStart=/var/www/splitbill/ocr-service/.venv/bin/uvicorn main:app --host 127.0.0.1 --port 8001
Restart=on-failure
RestartSec=3

[Install]
WantedBy=multi-user.target
```

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now splitbill-ocr
sudo systemctl status splitbill-ocr
curl -fsS http://127.0.0.1:8001/health     # smoke test
php artisan ocr:health                     # same check, but from Laravel's side
```

`php artisan ocr:health` reuses the production `OCR_BASE_URL` + timeout from
`config/services.php`, exits `0` on a healthy response and `1` otherwise — wire
it into a cron/uptime probe if you want outage alerts. See
[`ocr-service/README.md`](../ocr-service/README.md#manual-e2e-verification) for
the matching `ocr:scan` one-shot scanner used to verify a real receipt.

Laravel calls this over HTTP from the `ScanReceipt` action; if the service is down,
the UI degrades gracefully and the user can still enter the total manually.

## 7. Queue worker (optional, for slow work)

`ScanReceipt` is currently synchronous inside the Livewire request, which is fine on a
healthy VPS where OCR completes in ~1 s. If OCR latency becomes user-visible, swap
the action to dispatch a job and add a worker:

`/etc/systemd/system/splitbill-queue.service`:

```ini
[Unit]
Description=SplitBill Laravel queue worker
After=network.target

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=/var/www/splitbill
ExecStart=/usr/bin/php artisan queue:work --queue=default --sleep=3 --tries=3 --max-time=3600
Restart=always
RestartSec=3

[Install]
WantedBy=multi-user.target
```

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now splitbill-queue
```

`QUEUE_CONNECTION=database` in `.env` keeps it on the same SQLite/MariaDB so there's
no Redis to manage.

## 8. Rate limiting summary

These are already wired in code — listed here so the runbook is self-contained.

| Surface | Endpoint | Limit | Defined in |
|---|---|---|---|
| API | `POST /api/v1/register`, `POST /api/v1/login` | 6/min/IP | [routes/api.php](../routes/api.php) |
| API | `POST /api/v1/friends/requests` | 10/min/user | [routes/api.php](../routes/api.php) + [AppServiceProvider](../app/Providers/AppServiceProvider.php) |
| Web (Fortify) | `POST /login` | 5/min/email+IP | [FortifyServiceProvider](../app/Providers/FortifyServiceProvider.php) |
| Web (Livewire) | `AddFriend::send` | 10/min/user | [AddFriend](../app/Livewire/Friends/AddFriend.php) |

CSRF protection is enabled by default for all `web` routes; API uses Bearer tokens
(Sanctum) instead.

## 9. Backups (do this from day 1)

The whole dataset is one SQLite file. Daily snapshot off the VPS:

```bash
# /etc/cron.daily/splitbill-backup
#!/usr/bin/env bash
set -euo pipefail
ts=$(date -u +%Y%m%d_%H%M%S)
out="/var/backups/splitbill/splitbill_${ts}.sqlite"
mkdir -p /var/backups/splitbill
sqlite3 /var/www/splitbill/storage/app/database.sqlite ".backup '$out'"
gzip -9 "$out"
# Ship off-host (replace with rclone/restic/scp/B2/S3 — whatever is configured)
# rclone copy "${out}.gz" remote:splitbill-backups/
find /var/backups/splitbill -type f -name '*.sqlite.gz' -mtime +14 -delete
```

```bash
sudo chmod +x /etc/cron.daily/splitbill-backup
```

If you migrate to MariaDB later, swap the `sqlite3 .backup` call for
`mysqldump --single-transaction` and update the `.env`.

## 10. Routine deploy (after the first one)

```bash
cd /var/www/splitbill
git pull --ff-only
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan config:cache route:cache view:cache
sudo systemctl reload php8.4-fpm
sudo systemctl restart splitbill-ocr     # only if ocr-service/ changed
# sudo systemctl restart splitbill-queue # if running a worker
```

Verify:

```bash
curl -fsS https://splitbill.example.com/up           # Laravel health endpoint
curl -fsS http://127.0.0.1:8001/health               # OCR service
```

## 11. Migrating from SQLite to MariaDB (when traffic outgrows it)

Only `.env` changes plus a one-shot data copy:

1. `sudo apt install mariadb-server`, create `splitbill` DB + user.
2. Edit `.env`: set `DB_CONNECTION=mysql` (the alias name keeps Laravel happy with
   MariaDB), `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`.
3. `php artisan migrate --force` on the empty DB.
4. Re-import data via your last SQLite backup (e.g. dump tables with
   `sqlite3 ... .dump | sed ... | mysql ...`, or just re-run real-user activity if the
   table is tiny).
5. `php artisan config:cache` and reload PHP-FPM. No application code changes.

## 12. What's intentionally out of scope here

- Multi-server / load-balanced deploys — single VPS is the target.
- Redis / Horizon — not justified at this scale.
- Real receipt-image retention — Phase 05 keeps uploads transient (see BL-016 if
  this changes).
- Push notifications — in-app notifications only (BL-012).
