<?php

declare(strict_types=1);

use App\Actions\Expenses\ScanReceipt;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

/**
 * End-to-end test against the REAL Python OCR microservice.
 *
 * Skipped by default — only runs when:
 *   1. A fixture receipt with a documented expected total lives in
 *      tests/Fixtures/receipts/, AND
 *   2. The local OCR service responds to GET /health within 1 s.
 *
 * On CI this stays green by skipping; on a dev machine with the service up,
 * it proves Laravel <-> Python actually talk to each other.
 */
beforeEach(function () {
    $baseUrl = rtrim((string) config('services.ocr.base_url'), '/');

    if ($baseUrl === '') {
        test()->markTestSkipped('OCR_BASE_URL not configured.');
    }

    try {
        $response = Http::timeout(1)->acceptJson()->get($baseUrl.'/health');
    } catch (ConnectionException) {
        test()->markTestSkipped("OCR service unreachable at {$baseUrl}.");
    }

    if ($response->failed()) {
        test()->markTestSkipped("OCR service unhealthy at {$baseUrl} (HTTP {$response->status()}).");
    }
});

/**
 * @return list<array{path: string, expected_total: int}>
 */
function ocrFixtures(): array
{
    $dir = base_path('tests/Fixtures/receipts');

    if (! is_dir($dir)) {
        return [];
    }

    $manifest = $dir.'/expected.php';

    if (! is_file($manifest)) {
        return [];
    }

    $entries = require $manifest;

    if (! is_array($entries)) {
        return [];
    }

    $fixtures = [];

    foreach ($entries as $entry) {
        if (! is_array($entry) || ! isset($entry['file'], $entry['expected_total'])) {
            continue;
        }

        $path = $dir.'/'.$entry['file'];

        if (! is_file($path)) {
            continue;
        }

        $fixtures[] = [
            'path' => $path,
            'expected_total' => (int) $entry['expected_total'],
        ];
    }

    return $fixtures;
}

it('parses a real fixture receipt within +/- 5 % of the known total', function () {
    $fixtures = ocrFixtures();

    if ($fixtures === []) {
        test()->markTestSkipped('No fixture receipts registered in tests/Fixtures/receipts/expected.php yet.');
    }

    $fixture = $fixtures[0];
    $image = new UploadedFile(
        path: $fixture['path'],
        originalName: basename($fixture['path']),
        mimeType: (string) (mime_content_type($fixture['path']) ?: 'image/jpeg'),
        test: true,
    );

    $result = app(ScanReceipt::class)->execute($image);

    expect($result['available'])->toBeTrue()
        ->and($result['total_guess'])->not->toBeNull();

    $delta = abs($result['total_guess'] - $fixture['expected_total']);
    $tolerance = (int) ceil($fixture['expected_total'] * 0.05);

    expect($delta)->toBeLessThanOrEqual(
        $tolerance,
        "OCR total {$result['total_guess']} differs from expected {$fixture['expected_total']} by more than 5 %.",
    );
});
