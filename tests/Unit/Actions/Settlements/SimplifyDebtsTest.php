<?php

declare(strict_types=1);

use App\Actions\Settlements\SimplifyDebts;

beforeEach(function () {
    $this->simplify = new SimplifyDebts;
});

it('returns no transfers when everyone is at zero', function () {
    expect($this->simplify->execute([1 => 0, 2 => 0, 3 => 0]))->toBe([]);
});

it('produces a single transfer for a two-person debt', function () {
    $transfers = $this->simplify->execute([1 => -30_000, 2 => 30_000]);

    expect($transfers)->toBe([
        ['from' => 1, 'to' => 2, 'amount' => 30_000],
    ]);
});

it('uses N-1 transfers for the canonical three-person example', function () {
    // Alice paid 90k, Bob and Charlie each owe 30k.
    // Alice +60k, Bob -30k, Charlie -30k → two transfers, both pointing at Alice (id 1).
    $transfers = $this->simplify->execute([1 => 60_000, 2 => -30_000, 3 => -30_000]);

    expect($transfers)->toHaveCount(2)
        ->and(array_sum(array_column($transfers, 'amount')))->toBe(60_000);

    foreach ($transfers as $transfer) {
        expect($transfer['to'])->toBe(1);
    }
});

it('breaks ties between equal magnitudes by ascending user id', function () {
    $transfers = $this->simplify->execute([
        1 => 50_000,
        2 => 50_000,
        3 => -50_000,
        4 => -50_000,
    ]);

    expect($transfers)->toBe([
        ['from' => 3, 'to' => 1, 'amount' => 50_000],
        ['from' => 4, 'to' => 2, 'amount' => 50_000],
    ]);
});

it('skips users with zero balance from the transfer list', function () {
    $transfers = $this->simplify->execute([1 => 0, 2 => 50_000, 3 => -50_000]);

    expect($transfers)->toBe([
        ['from' => 3, 'to' => 2, 'amount' => 50_000],
    ]);
});

it('handles unequal magnitudes by partial-matching the larger side', function () {
    // 1 must pay 100k. 2 and 3 are owed 80k and 20k respectively.
    $transfers = $this->simplify->execute([1 => -100_000, 2 => 80_000, 3 => 20_000]);

    expect($transfers)->toBe([
        ['from' => 1, 'to' => 2, 'amount' => 80_000],
        ['from' => 1, 'to' => 3, 'amount' => 20_000],
    ])
        ->and(array_sum(array_column($transfers, 'amount')))->toBe(100_000);
});

it('produces transfers whose total equals the creditor sum', function () {
    $balances = [1 => -100_000, 2 => -50_000, 3 => 80_000, 4 => 70_000];

    $transfers = $this->simplify->execute($balances);

    expect(array_sum(array_column($transfers, 'amount')))->toBe(150_000);
});

it('caps transfer count at N-1 for any solvable input', function () {
    $balances = [1 => 30_000, 2 => 20_000, 3 => -25_000, 4 => -15_000, 5 => -10_000];

    $transfers = $this->simplify->execute($balances);

    expect(count($transfers))->toBeLessThanOrEqual(4);
});
