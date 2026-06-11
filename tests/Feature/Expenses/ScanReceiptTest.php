<?php

declare(strict_types=1);

use App\Actions\Expenses\ScanReceipt;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('services.ocr.base_url', 'http://127.0.0.1:8001');
    config()->set('services.ocr.timeout', 5);
});

function fakeReceiptImage(): UploadedFile
{
    return UploadedFile::fake()->image('receipt.jpg');
}

it('forwards the upload to /ocr and normalizes the response', function () {
    Http::fake([
        '127.0.0.1:8001/ocr' => Http::response([
            'raw_text' => "INDOMARET\nKopi 18.000\nTOTAL 33.855\n",
            'total_guess' => 33855,
            'line_items' => [
                ['name' => 'Kopi Susu', 'amount' => 18000],
                ['name' => 'Roti Tawar', 'amount' => 12500],
            ],
            'engine' => 'tesseract',
        ]),
    ]);

    $result = app(ScanReceipt::class)->execute(fakeReceiptImage());

    expect($result['available'])->toBeTrue()
        ->and($result['total_guess'])->toBe(33855)
        ->and($result['line_items'])->toEqual([
            ['name' => 'Kopi Susu', 'amount' => 18000],
            ['name' => 'Roti Tawar', 'amount' => 12500],
        ])
        ->and($result['engine'])->toBe('tesseract')
        ->and($result['error'])->toBeNull()
        ->and($result['raw_text'])->toContain('TOTAL 33.855');

    Http::assertSent(fn (Request $r) => $r->url() === 'http://127.0.0.1:8001/ocr' && $r->isMultipart());
});

it('degrades gracefully when the OCR service is unreachable', function () {
    Http::fake(function () {
        throw new ConnectionException('Connection refused');
    });

    $result = app(ScanReceipt::class)->execute(fakeReceiptImage());

    expect($result)->toEqual([
        'available' => false,
        'total_guess' => null,
        'line_items' => [],
        'raw_text' => '',
        'engine' => null,
        'error' => 'OCR service unreachable: Connection refused',
    ]);
});

it('degrades gracefully on 5xx response', function () {
    Http::fake([
        '127.0.0.1:8001/ocr' => Http::response('boom', 500),
    ]);

    $result = app(ScanReceipt::class)->execute(fakeReceiptImage());

    expect($result['available'])->toBeFalse()
        ->and($result['total_guess'])->toBeNull()
        ->and($result['error'])->toContain('HTTP 500');
});

it('returns null total when the service responds with non-positive or invalid amount', function () {
    Http::fake([
        '127.0.0.1:8001/ocr' => Http::response([
            'raw_text' => 'blur',
            'total_guess' => 0,
            'line_items' => [],
            'engine' => 'tesseract',
        ]),
    ]);

    $result = app(ScanReceipt::class)->execute(fakeReceiptImage());

    expect($result['available'])->toBeTrue()
        ->and($result['total_guess'])->toBeNull();
});

it('coerces numeric-string totals to integer rupiah', function () {
    Http::fake([
        '127.0.0.1:8001/ocr' => Http::response([
            'raw_text' => '',
            'total_guess' => '50000',
            'line_items' => [],
            'engine' => 'tesseract',
        ]),
    ]);

    $result = app(ScanReceipt::class)->execute(fakeReceiptImage());

    expect($result['total_guess'])->toBe(50000);
});

it('filters out malformed line items', function () {
    Http::fake([
        '127.0.0.1:8001/ocr' => Http::response([
            'raw_text' => '',
            'total_guess' => 25000,
            'line_items' => [
                ['name' => 'Good', 'amount' => 10000],
                ['name' => '', 'amount' => 5000],   // empty name → drop
                ['name' => 'NoAmount'],              // missing amount → drop
                ['name' => 'Negative', 'amount' => -1], // non-positive → drop
                'garbage',                            // not an array → drop
            ],
            'engine' => 'tesseract',
        ]),
    ]);

    $result = app(ScanReceipt::class)->execute(fakeReceiptImage());

    expect($result['line_items'])->toEqual([
        ['name' => 'Good', 'amount' => 10000],
    ]);
});

it('degrades gracefully when the response body is not JSON-shaped', function () {
    Http::fake([
        '127.0.0.1:8001/ocr' => Http::response('plain text body'),
    ]);

    $result = app(ScanReceipt::class)->execute(fakeReceiptImage());

    expect($result['available'])->toBeFalse()
        ->and($result['error'])->toContain('unexpected payload');
});
