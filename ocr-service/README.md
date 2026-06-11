# SplitBill OCR microservice

Local FastAPI service that runs Tesseract 5 (`ind`) on receipt images and
returns a structured guess of the total + line items. Laravel calls this
over HTTP only — Python is never imported in PHP. See the
[architecture overview](../README.md#architecture) and the
[production runbook](../docs/deploy.md).

## Prereqs (one-time, Windows)

1. Python 3.11+ on PATH (`python --version`).
2. Tesseract OCR — UB-Mannheim installer. Select "Additional language data
   (download)" and tick **Indonesian** during install. Verify:

   ```powershell
   tesseract --version
   tesseract --list-langs   # must list 'ind'
   ```

> **Don't worry if `tesseract` isn't on your PATH.** `engine.py` locates the
> binary automatically: it honours a `TESSERACT_CMD` env var first, then falls
> back to the default UB-Mannheim install path
> (`C:\Program Files\Tesseract-OCR\tesseract.exe`). On Linux/macOS it's found on
> PATH as usual. Only set `TESSERACT_CMD` if you installed Tesseract somewhere
> non-standard:
>
> ```powershell
> $env:TESSERACT_CMD = "D:\tools\Tesseract-OCR\tesseract.exe"
> ```

## Install

```powershell
cd ocr-service
python -m venv .venv
.venv\Scripts\activate
pip install -r requirements.txt
```

## Run (dev)

```powershell
.venv\Scripts\activate
uvicorn main:app --host 127.0.0.1 --port 8001 --reload
```

Smoke test:

```powershell
curl http://127.0.0.1:8001/health
# → {"status":"ok","engine":"tesseract"}
```

## Endpoints

### `GET /health`
Returns `{"status":"ok","engine":"<name>"}`. Used by Laravel to detect that
the service is reachable before forwarding an image.

### `POST /ocr`
Multipart upload with field `file` (image, ≤ 5 MB). Response shape
(stable contract — DO NOT break without updating rule 03):

```json
{
  "raw_text": "TOTAL 50.000\n...",
  "total_guess": 50000,
  "line_items": [{"name": "Kopi", "amount": 18000}],
  "engine": "tesseract"
}
```

`total_guess` is `null` if nothing plausible is found. All amounts are
integer rupiah.

## Swapping engines

The engine is selected by `OCR_ENGINE` env (default `tesseract`). To move
to RapidOCR later:

1. `pip install rapidocr-onnxruntime` in the venv.
2. Add a `RapidOcrEngine` class in `engine.py` whose `recognize()` returns
   raw text.
3. Wire it inside `build_engine()`.
4. `OCR_ENGINE=rapidocr uvicorn main:app ...`.

The `/ocr` JSON contract stays identical — no Laravel changes.

## Privacy / ops

- Bind to `127.0.0.1` only. Never expose this port publicly.
- Laravel side validates mime + size before forwarding; this service
  re-validates as a defense in depth.
- The image is processed in memory and discarded — receipts are not
  written to disk by the service.

## Manual E2E verification

Once both processes are running you can prove the Laravel ↔ Python boundary
without touching the web UI:

```powershell
# Terminal 1 — OCR service
cd ocr-service
.venv\Scripts\activate
uvicorn main:app --host 127.0.0.1 --port 8001

# Terminal 2 — Laravel (repo root)
php artisan ocr:health
# → "OCR service OK at http://127.0.0.1:8001 (engine=tesseract)."   exit 0
```

If the service is down, `ocr:health` exits `1` and prints a friendly error
— suitable for a cron / uptime probe.

Run a real receipt through the pipeline:

```powershell
php artisan ocr:scan tests/Fixtures/receipts/<your-file>.jpg
```

Drop your own struk photos in [`tests/Fixtures/receipts/`](../tests/Fixtures/receipts/README.md)
first; the directory has a README explaining the expected layout and the
sibling `expected.php` manifest the optional integration test reads.

There is also a Pest integration spec (`tests/Feature/Expenses/ScanReceiptIntegrationTest.php`)
that auto-skips unless `/health` answers within 1 s — it stays green on CI,
but runs end-to-end against the real service whenever the dev environment
is up.
