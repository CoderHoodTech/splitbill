<?php

declare(strict_types=1);

use App\Support\Money;

it('formats zero rupiah', function () {
    expect(Money::format(0))->toBe('Rp0');
});

it('formats small amounts without thousands separator', function () {
    expect(Money::format(500))->toBe('Rp500');
    expect(Money::format(999))->toBe('Rp999');
});

it('formats 50.000 with dot as thousands separator', function () {
    expect(Money::format(50_000))->toBe('Rp50.000');
});

it('formats 1.250.000 with multiple thousands separators', function () {
    expect(Money::format(1_250_000))->toBe('Rp1.250.000');
});

it('formats large amounts (10 digits)', function () {
    expect(Money::format(1_234_567_890))->toBe('Rp1.234.567.890');
});

it('formats negative amounts (debt)', function () {
    expect(Money::format(-50_000))->toBe('-Rp50.000');
});
