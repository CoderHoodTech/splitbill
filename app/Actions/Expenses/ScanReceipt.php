<?php

declare(strict_types=1);

namespace App\Actions\Expenses;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Bridge to the Python OCR microservice. Pure HTTP — no Python in PHP.
 *
 * Returns a stable array shape so callers (Livewire / API) never have to
 * deal with the engine being down. Failures degrade to an empty guess
 * plus an `available=false` flag (rule 03 + rule 04).
 *
 * @phpstan-type ScannedReceipt array{
 *     available: bool,
 *     total_guess: int|null,
 *     line_items: list<array{name: string, amount: int}>,
 *     raw_text: string,
 *     engine: string|null,
 *     error: string|null,
 * }
 */
final class ScanReceipt
{
    /**
     * Forward the uploaded image to the OCR service and normalize the response.
     *
     * @return ScannedReceipt
     */
    public function execute(UploadedFile $image): array
    {
        $baseUrl = rtrim((string) config('services.ocr.base_url'), '/');

        if ($baseUrl === '') {
            return $this->emptyResult('OCR service URL is not configured.');
        }

        $timeout = (int) config('services.ocr.timeout', 20);
        $filename = $image->getClientOriginalName() ?: 'receipt.jpg';

        try {
            $response = Http::timeout($timeout)
                ->acceptJson()
                ->attach('file', $image->get(), $filename, ['Content-Type' => $image->getMimeType() ?: 'image/jpeg'])
                ->post($baseUrl.'/ocr');
        } catch (ConnectionException $e) {
            return $this->emptyResult('OCR service unreachable: '.$e->getMessage());
        } catch (RequestException $e) {
            return $this->emptyResult('OCR service error: '.$e->getMessage());
        } catch (Throwable $e) {
            Log::warning('ScanReceipt: unexpected failure', ['message' => $e->getMessage()]);

            return $this->emptyResult('OCR service unavailable.');
        }

        if ($response->failed()) {
            return $this->emptyResult('OCR service returned HTTP '.$response->status().'.');
        }

        $body = $response->json();

        if (! is_array($body)) {
            return $this->emptyResult('OCR service returned an unexpected payload.');
        }

        return [
            'available' => true,
            'total_guess' => $this->normalizeAmount($body['total_guess'] ?? null),
            'line_items' => $this->normalizeLineItems($body['line_items'] ?? []),
            'raw_text' => is_string($body['raw_text'] ?? null) ? $body['raw_text'] : '',
            'engine' => is_string($body['engine'] ?? null) ? $body['engine'] : null,
            'error' => null,
        ];
    }

    /**
     * @return ScannedReceipt
     */
    private function emptyResult(string $error): array
    {
        return [
            'available' => false,
            'total_guess' => null,
            'line_items' => [],
            'raw_text' => '',
            'engine' => null,
            'error' => $error,
        ];
    }

    private function normalizeAmount(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }

        if (is_numeric($value)) {
            $int = (int) $value;

            return $int > 0 ? $int : null;
        }

        return null;
    }

    /**
     * @return list<array{name: string, amount: int}>
     */
    private function normalizeLineItems(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        $normalized = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $name = isset($item['name']) && is_string($item['name']) ? trim($item['name']) : '';
            $amount = $this->normalizeAmount($item['amount'] ?? null);

            if ($name === '' || $amount === null) {
                continue;
            }

            $normalized[] = ['name' => $name, 'amount' => $amount];
        }

        return $normalized;
    }
}
