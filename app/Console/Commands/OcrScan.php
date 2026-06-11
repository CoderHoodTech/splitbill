<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Expenses\ScanReceipt;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\warning;

/**
 * Run a local image file through the OCR pipeline and pretty-print the result.
 * Great for manual receipt testing without spinning up the web UI.
 */
#[Signature('ocr:scan {path : Absolute or relative path to a receipt image (<= 5 MB)}')]
#[Description('Send a local receipt image to the OCR microservice and print the parsed result.')]
final class OcrScan extends Command
{
    private const MAX_UPLOAD_BYTES = 5 * 1024 * 1024;

    public function handle(ScanReceipt $action): int
    {
        $path = (string) $this->argument('path');

        if (! is_file($path)) {
            error("File not found: {$path}");

            return self::FAILURE;
        }

        $size = (int) filesize($path);

        if ($size === 0) {
            error('File is empty.');

            return self::FAILURE;
        }

        if ($size > self::MAX_UPLOAD_BYTES) {
            error('File exceeds the 5 MB limit ('.number_format($size / 1024 / 1024, 2).' MB).');

            return self::FAILURE;
        }

        $mime = (string) (mime_content_type($path) ?: '');

        if (! str_starts_with($mime, 'image/')) {
            error("File must be an image (got: {$mime}).");

            return self::FAILURE;
        }

        $image = new UploadedFile(
            path: $path,
            originalName: basename($path),
            mimeType: $mime,
            test: true,
        );

        info("Scanning {$path} ({$mime}, ".number_format($size / 1024, 1).' KB)...');

        $result = $action->execute($image);

        if (! $result['available']) {
            warning('OCR service unavailable: '.($result['error'] ?? 'unknown error'));

            return self::FAILURE;
        }

        $engine = $result['engine'] ?? 'unknown';
        $total = $result['total_guess'];
        $totalDisplay = $total === null ? '(none)' : 'Rp '.number_format($total, 0, ',', '.');

        $this->newLine();
        $this->components->twoColumnDetail('Engine', $engine);
        $this->components->twoColumnDetail('Total guess', $totalDisplay);
        $this->components->twoColumnDetail('Line items', (string) count($result['line_items']));

        if ($result['line_items'] !== []) {
            $this->newLine();
            $this->table(
                ['#', 'Name', 'Amount (Rp)'],
                array_map(
                    fn (array $item, int $i) => [
                        $i + 1,
                        $item['name'],
                        number_format($item['amount'], 0, ',', '.'),
                    ],
                    $result['line_items'],
                    array_keys($result['line_items']),
                ),
            );
        }

        if (($result['raw_text'] ?? '') !== '') {
            $this->newLine();
            $this->line('<comment>Raw OCR text:</comment>');
            $this->line($result['raw_text']);
        }

        return self::SUCCESS;
    }
}
