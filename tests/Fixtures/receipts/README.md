# Receipt fixtures

Real Indonesian receipt photos used for manual OCR smoke testing and the optional
integration test in [`tests/Feature/Expenses/ScanReceiptIntegrationTest.php`](../../Feature/Expenses/ScanReceiptIntegrationTest.php).

These files are committed (they are NOT user data — redact any personal info such as
card numbers, addresses, or barcodes before adding a new one).

## Files

| File | Source | Expected `total_guess` (Rp) | Notes |
|---|---|---:|---|
| _add yours here_ | _e.g. Indomaret 2026-05_ | _e.g. 33855_ | _photo conditions, expected line items_ |

## Adding a new fixture

1. Photograph a clean, well-lit receipt (struk Indomaret / Alfamart / warung / kafe).
2. Crop tight, save as JPEG (`.jpg`) under 2 MB. Aim for ~1200 px on the long side.
3. Redact any sensitive data with a black box (card numbers, customer name, etc.).
4. Drop the file in this directory.
5. Add a row above with the expected `total_guess` so future smoke runs can verify.

## Verifying the fixture

```powershell
php artisan ocr:scan tests/Fixtures/receipts/<your-file>.jpg
```

The printed `Total guess` should match (or be within ~5 % of) the value in the table
above — Tesseract is fuzzy on photographed receipts. See the
[OCR microservice README](../../../ocr-service/README.md) for the parser rules.

## Why these are in git

The integration test is skipped unless the local Python service is up, so CI never
needs the images. We commit them anyway so:

- New contributors can run `php artisan ocr:scan` immediately without sourcing
  their own photos.
- The expected values stay co-located with the images that produced them.
