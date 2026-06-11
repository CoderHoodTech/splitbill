<?php

declare(strict_types=1);

use App\Actions\Splitting\CalculateShares;
use App\Enums\SplitMethod;
use App\Exceptions\ExpenseException;

beforeEach(function () {
    $this->splitter = new CalculateShares;
});

it('splits an equal amount evenly with no remainder', function () {
    $shares = $this->splitter->execute(SplitMethod::Equal, 90_000, [1, 2, 3]);

    expect($shares)->toBe([1 => 30_000, 2 => 30_000, 3 => 30_000])
        ->and(array_sum($shares))->toBe(90_000);
});

it('distributes the equal-split remainder rupiah-by-rupiah to ascending user ids', function () {
    // 100 / 3 = 33 r1 — first user (lowest id) gets the extra rupiah.
    $shares = $this->splitter->execute(SplitMethod::Equal, 100, [7, 3, 5]);

    expect($shares)->toBe([3 => 34, 5 => 33, 7 => 33])
        ->and(array_sum($shares))->toBe(100);
});

it('distributes a 2-rupiah equal remainder to the two lowest user ids', function () {
    // 101 / 3 = 33 r2 — first two users get +1 each.
    $shares = $this->splitter->execute(SplitMethod::Equal, 101, [10, 1, 4]);

    expect($shares)->toBe([1 => 34, 4 => 34, 10 => 33])
        ->and(array_sum($shares))->toBe(101);
});

it('handles a single participant by giving them the full total', function () {
    $shares = $this->splitter->execute(SplitMethod::Equal, 50_000, [42]);

    expect($shares)->toBe([42 => 50_000]);
});

it('returns exact shares when they sum to total', function () {
    $shares = $this->splitter->execute(
        SplitMethod::Exact,
        100_000,
        [1, 2, 3],
        inputs: [1 => 25_000, 2 => 50_000, 3 => 25_000],
    );

    expect($shares)->toBe([1 => 25_000, 2 => 50_000, 3 => 25_000])
        ->and(array_sum($shares))->toBe(100_000);
});

it('throws when exact shares do not sum to total', function () {
    $this->splitter->execute(
        SplitMethod::Exact,
        100_000,
        [1, 2],
        inputs: [1 => 40_000, 2 => 50_000],
    );
})->throws(ExpenseException::class);

it('throws when an exact share input is missing', function () {
    $this->splitter->execute(
        SplitMethod::Exact,
        100_000,
        [1, 2],
        inputs: [1 => 100_000],
    );
})->throws(ExpenseException::class);

it('splits percent shares and assigns the rounding remainder to the lowest user id', function () {
    // 100 * 33% = 33, 100 * 33% = 33, 100 * 34% = 34 — sums to 100 exactly.
    $shares = $this->splitter->execute(
        SplitMethod::Percent,
        100,
        [3, 1, 2],
        inputs: [3 => 34, 1 => 33, 2 => 33],
    );

    expect($shares)->toBe([1 => 33, 2 => 33, 3 => 34])
        ->and(array_sum($shares))->toBe(100);
});

it('assigns the floor remainder to the first participant when percent shares need rounding', function () {
    // 100 * 33.33 = 33.33 floors to 33 * 3 = 99 → 1 rupiah remainder lands on user 1.
    $shares = $this->splitter->execute(
        SplitMethod::Percent,
        100,
        [3, 2, 1],
        inputs: [1 => 33.33, 2 => 33.33, 3 => 33.34],
    );

    expect($shares)->toBe([1 => 34, 2 => 33, 3 => 33])
        ->and(array_sum($shares))->toBe(100);
});

it('throws when percentages do not sum to 100', function () {
    $this->splitter->execute(
        SplitMethod::Percent,
        100,
        [1, 2],
        inputs: [1 => 40, 2 => 30],
    );
})->throws(ExpenseException::class);

it('throws when the participant list is empty', function () {
    $this->splitter->execute(SplitMethod::Equal, 100, []);
})->throws(ExpenseException::class);

it('throws when the same participant appears twice', function () {
    $this->splitter->execute(SplitMethod::Equal, 100, [1, 1, 2]);
})->throws(ExpenseException::class);

it('throws when the total is zero', function () {
    $this->splitter->execute(SplitMethod::Equal, 0, [1, 2]);
})->throws(ExpenseException::class);

it('throws on a negative exact share', function () {
    $this->splitter->execute(
        SplitMethod::Exact,
        100,
        [1, 2],
        inputs: [1 => -50, 2 => 150],
    );
})->throws(ExpenseException::class);
