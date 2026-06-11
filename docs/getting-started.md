# Getting Started — SplitBill

> A full walkthrough for bringing SplitBill up from scratch on a fresh Windows
> laptop. Linux/macOS is almost identical — I've flagged the few different
> commands with 🐧.
>
> The short version of this guide lives in [README.md](../README.md#local-setup).
> This document is for someone who just cloned the repo and wants to know
> exactly what to type next.

## Contents

1. [What this app is](#1-what-this-app-is)
2. [Prerequisites (one-time install)](#2-prerequisites-one-time-install)
3. [Clone & install dependencies](#3-clone--install-dependencies)
4. [Configure `.env`](#4-configure-env)
5. [Database & migrations](#5-database--migrations)
6. [Storage symlink (for file uploads)](#6-storage-symlink-for-file-uploads)
7. [OCR microservice (Python)](#7-ocr-microservice-python)
8. [Run the web app (Laravel + Vite)](#8-run-the-web-app-laravel--vite)
9. [Daily-driver Artisan cheat sheet](#9-daily-driver-artisan-cheat-sheet)
10. [Smoke-test the Laravel ↔ Python boundary](#10-smoke-test-the-laravel--python-boundary)
11. [Running tests](#11-running-tests)
12. [Troubleshooting](#12-troubleshooting)

## 1. What this app is

SplitBill is a **bill-splitting** app in the Splitwise style, built specifically
for Indonesian users:

- **Mobile-first** responsive web (Laravel + Livewire), works from a phone
  browser too.
- **Rupiah-only** — all money is stored as integer rupiah, no decimals
  (`Rp50.000` = `50000`). No floats, no currency conversion.
- **Indonesian receipt OCR** — upload a photo of a struk (Indomaret /
  Alfamart / café), and a small Python service (Tesseract 5 + `ind`) parses
  the total and line items. The user can edit before saving.
- **Flutter-ready JSON API** — Sanctum bearer tokens, versioned at
  `/api/v1`. Business logic is shared with the web UI through Action
  classes, so the API and the website never drift.

Full architecture diagram: [`../ARCHITECTURE.svg`](../ARCHITECTURE.svg).
Production runbook: [`deploy.md`](deploy.md).

## 2. Prerequisites (one-time install)

| You need | How to install (Windows) | Verify |
|---|---|---|
| PHP 8.4 + Composer + the Laravel installer | [Laravel Herd](https://herd.laravel.com/) — free, bundles everything | `php -v` &nbsp;`composer -V` |
| Node.js 22 LTS (for Vite + asset build) | [nodejs.org](https://nodejs.org/) | `node -v` &nbsp;`npm -v` |
| Git | [git-scm.com](https://git-scm.com/) | `git --version` |
| Python 3.11+ (for OCR) | [python.org](https://www.python.org/downloads/) — **tick "Add Python to PATH"** | `python --version` |
| Tesseract 5 + Indonesian language pack | [UB-Mannheim installer](https://github.com/UB-Mannheim/tesseract/wiki) — tick "Additional language data (download)" → **Indonesian** | `tesseract --version` &nbsp;`tesseract --list-langs` (must include `ind`) |
| MySQL (optional, for the dev DB) | Herd Pro ships MySQL; or fall back to SQLite | `mysql --version` |

🐧 On Linux: install PHP via `ppa:ondrej/php` and Tesseract via
`apt -y install tesseract-ocr tesseract-ocr-ind`. Full apt list in
[`deploy.md`](deploy.md#1-prerequisites-on-the-vps).

> **Tesseract note:** if `tesseract --list-langs` doesn't show `ind`, OCR
> will still run but the results will be garbage (it will try to read
> Indonesian as English). Install the `ind` language pack before moving on.

## 3. Clone & install dependencies

```powershell
git clone https://github.com/fabianokky/splitbill.git
cd splitbill

# PHP deps
composer install

# JS deps
npm install
```

`composer install` takes ~2 minutes on a cold cache (vendor download); same
for `npm install`.

## 4. Configure `.env`

```powershell
copy .env.example .env
php artisan key:generate
```

`key:generate` fills in `APP_KEY` — it's required; without it Laravel throws
`MissingAppKeyException` on the first request.

**Keys worth reviewing in `.env`:**

| Key | Default | When to change |
|---|---|---|
| `APP_URL` | `http://localhost:8000` | Set to `http://splitbill.test` if you use Herd |
| `DB_CONNECTION` | `mysql` | Switch to `sqlite` if you'd rather skip MySQL setup |
| `DB_DATABASE` | `splitbill` | Database name — create it manually or via Herd |
| `OCR_BASE_URL` | `http://127.0.0.1:8001` | URL of the Python service — usually leave alone |
| `OCR_TIMEOUT` | `20` | Seconds Laravel waits for an OCR response |
| `MAIL_MAILER` | `log` | Password-reset emails land in `storage/logs/laravel.log` in dev |

> **Herd + MySQL tip:** Herd Pro runs MySQL on port 3306 with user `root`
> and no password. Quick setup:
> ```powershell
> mysql -u root -e "CREATE DATABASE splitbill"
> ```
> then set `DB_USERNAME=root` and `DB_PASSWORD=` (empty) in `.env`.

## 5. Database & migrations

Once `.env` is set and the `splitbill` database exists:

```powershell
php artisan migrate
```

That runs every migration in [`database/migrations/`](../database/migrations/) —
creating tables for users, friend_requests, groups, expenses, settlements,
and so on.

Want some dummy data to click around? There's no standard seeder yet — use
factories directly:

```powershell
php artisan tinker --execute 'App\Models\User::factory(5)->create();'
```

Or use the UI: register two accounts, swap friend codes, create a group.

> **Heads up on `migrate:fresh` / `migrate:refresh`** — both drop every table
> without asking. Only use them in dev when you genuinely need to wipe the
> schema.

## 6. Storage symlink (for file uploads)

Laravel writes uploaded files (e.g. in-flight receipt photos) under
`storage/app/public/`. To make them reachable at `/storage/...`, the
`public/storage` symlink has to exist:

```powershell
php artisan storage:link
```

You only run this once — the symlink survives until the folder is deleted.
On Windows, if it fails with a permission error, either run PowerShell **as
Administrator** or enable Developer Mode (Windows Settings → For developers
→ Developer Mode on).

## 7. OCR microservice (Python)

The OCR service is a separate process from Laravel — its own Python process
on port 8001. Laravel just talks to it over HTTP via `POST /ocr`.

**First-time setup:**

```powershell
cd ocr-service
python -m venv .venv
.venv\Scripts\activate
pip install -r requirements.txt
```

Verify that Python can reach Tesseract:

```powershell
python -c "import pytesseract; print(pytesseract.get_tesseract_version())"
# → 5.3.x or newer
```

**Running the service (separate terminal, keep it open):**

```powershell
cd ocr-service
.venv\Scripts\activate
uvicorn main:app --host 127.0.0.1 --port 8001 --reload
```

Quick check from another terminal:

```powershell
curl http://127.0.0.1:8001/health
# → {"status":"ok","engine":"tesseract"}
```

Or use the Artisan command (friendlier output, exit codes for cron):

```powershell
php artisan ocr:health
# → "OCR service OK at http://127.0.0.1:8001 (engine=tesseract)."
```

> The service binds to `127.0.0.1` only — never publicly exposed, even in
> production. The engine is swappable to RapidOCR without touching Laravel —
> see [`../ocr-service/README.md`](../ocr-service/README.md#swapping-engines).

## 8. Run the web app (Laravel + Vite)

The easiest path — one command, three processes running concurrently
(Laravel server + queue listener + Vite hot-reload):

```powershell
composer run dev
```

You'll see three colour-coded panels:

- `server` — `php artisan serve` (Laravel at http://localhost:8000)
- `queue`  — `queue:listen` (for background jobs, optional)
- `vite`   — `npm run dev` (Vite HMR for CSS/JS)

Open http://localhost:8000 (or `http://splitbill.test` with Herd), register
an account, and start clicking around.

**Or run them separately** if you want fine-grained control:

```powershell
# Terminal 1
php artisan serve

# Terminal 2
npm run dev

# Terminal 3 (only if you need queue processing)
php artisan queue:listen --tries=1
```

**Build for production / test the real build:**

```powershell
npm run build
# Assets compile to public/build/ — Vite no longer needs to be running
```

> **Vite manifest error?** If you see
> `Unable to locate file in Vite manifest`, neither the dev server nor a
> production build is in place. Fix: run `npm run dev` OR `npm run build`.

## 9. Daily-driver Artisan cheat sheet

| You want to ... | Command |
|---|---|
| List every route | `php artisan route:list --except-vendor` |
| Find routes by name | `php artisan route:list --name=friends` |
| Find routes by path | `php artisan route:list --path=api/v1` |
| Inspect config | `php artisan config:show services.ocr` |
| List Artisan commands | `php artisan list` |
| REPL for debugging | `php artisan tinker` |
| Clear all caches | `php artisan optimize:clear` |
| Clear one cache | `php artisan config:clear` (or `route:`, `view:`) |
| Reset & re-seed DB (DEV ONLY) | `php artisan migrate:fresh --seed` |
| Make a migration | `php artisan make:migration create_xxx_table` |
| Make a model + migration + factory | `php artisan make:model Xxx -mf` |
| Make a Livewire component | `php artisan livewire:make Xxx` |
| Make a Pest test | `php artisan make:test --pest XxxTest` |
| Tail the Laravel log live | `php artisan pail` |
| Format code | `.\vendor\bin\pint` |
| Check formatting without writing | `.\vendor\bin\pint --test` |

## 10. Smoke-test the Laravel ↔ Python boundary

Once the OCR service is running and Laravel is up, verify that they can
actually talk:

```powershell
# Health check (fast, no upload)
php artisan ocr:health

# Scan a real receipt photo (drop one in first — see tests/Fixtures/receipts/README.md)
php artisan ocr:scan tests\Fixtures\receipts\<file>.jpg
```

`ocr:scan` prints engine + total guess + line items in a neat table, plus
the raw OCR text. Great for debugging the parser without opening the UI.

Composer shortcuts:

```powershell
composer smoke:ocr
composer smoke:scan -- tests\Fixtures\receipts\<file>.jpg
```

## 11. Running tests

### Laravel (Pest)

```powershell
# Full suite, compact output
php artisan test --compact

# Filter to a file or test name
php artisan test --compact --filter=ScanReceipt

# Run Pint as a checker too
.\vendor\bin\pint --test

# Or via composer (Pint check + Pest)
composer test
```

Current baseline: **238 tests, 236 pass, 2 skipped** (the 2FA spec waiting on
BL-013, plus the OCR integration test which auto-skips when the Python
service isn't running).

### OCR microservice (pytest)

```powershell
cd ocr-service
.venv\Scripts\activate
pytest
```

The receipt parser has its own unit tests in
[`ocr-service/test_parser.py`](../ocr-service/test_parser.py).

## 12. Troubleshooting

| Symptom | Likely cause + fix |
|---|---|
| `Class "App\Models\User" not found` after a pull | Forgot to `composer install` after a dependency change. Re-run it. |
| `MissingAppKeyException` | Forgot `php artisan key:generate`. |
| `Unable to locate file in Vite manifest` | `npm run dev` isn't running AND assets haven't been `npm run build`-ed. |
| `SQLSTATE[HY000] [2002] Connection refused` | MySQL isn't running, or `DB_*` in `.env` is wrong. Quick test: `mysql -u root`. Alternative: switch `DB_CONNECTION=sqlite`. |
| `Could not connect to server` from `ocr:health` | The Python service isn't running. Check the terminal where `uvicorn` should be live. |
| `tesseract` not found on Windows | Tesseract isn't on PATH. Reinstall with the "Add to PATH" option, or open a fresh shell. |
| OCR output is gibberish English | The `ind` language pack isn't installed. `tesseract --list-langs` must include `ind`. |
| `php artisan storage:link` permission error | Run PowerShell as Admin, or enable Developer Mode in Windows Settings. |
| Stale browser after restarting `composer run dev` | Hard reload (Ctrl+Shift+R). The Vite dev port (5173) may have changed. |
| Tests hang mid-run | Check whether a queue worker is still running from an earlier session. |

Still stuck? Run these first:

```powershell
php artisan about            # Laravel environment + DB + cache summary
php artisan config:show app  # Active app config
php artisan pail             # Tail logs in real time
```

Then open an issue on GitHub with the `php artisan about` output attached.

---

Next steps: read [`api.md`](api.md) if you want to hit the JSON API (Sanctum
bearer tokens), or [`deploy.md`](deploy.md) if you want to deploy this to a
VPS.
