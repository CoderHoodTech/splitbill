<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Throwable;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;

/**
 * Pings the Python OCR microservice and reports whether Laravel can reach it.
 * Exits 0 on a `200 ok` response so CI/cron can detect outages.
 */
#[Signature('ocr:health')]
#[Description('Ping the Python OCR microservice and report its status.')]
final class OcrHealth extends Command
{
    public function handle(): int
    {
        $baseUrl = rtrim((string) config('services.ocr.base_url'), '/');
        $timeout = (int) config('services.ocr.timeout', 20);

        if ($baseUrl === '') {
            error('OCR_BASE_URL is not configured.');

            return self::FAILURE;
        }

        try {
            $response = Http::timeout($timeout)
                ->acceptJson()
                ->get($baseUrl.'/health');
        } catch (ConnectionException $e) {
            error("OCR service unreachable at {$baseUrl}: {$e->getMessage()}");

            return self::FAILURE;
        } catch (Throwable $e) {
            error("OCR service request failed: {$e->getMessage()}");

            return self::FAILURE;
        }

        if ($response->failed()) {
            error("OCR service returned HTTP {$response->status()} at {$baseUrl}.");

            return self::FAILURE;
        }

        $body = $response->json();

        if (! is_array($body) || ($body['status'] ?? null) !== 'ok') {
            error('OCR service responded but the payload is not healthy.');

            return self::FAILURE;
        }

        $engine = is_string($body['engine'] ?? null) ? $body['engine'] : 'unknown';

        info("OCR service OK at {$baseUrl} (engine={$engine}).");

        return self::SUCCESS;
    }
}
