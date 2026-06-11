<?php

declare(strict_types=1);

use App\Actions\Expenses\AddExpense;
use App\Actions\Groups\CreateGroup;
use App\Enums\SplitMethod;
use App\Exceptions\ExpenseException;
use App\Models\Friendship;
use App\Models\User;

beforeEach(function () {
    $this->action = app(AddExpense::class);
    $this->create = app(CreateGroup::class);
});

function friendsForExpense(User $a, User $b): void
{
    Friendship::query()->create(['user_id' => $a->id, 'friend_id' => $b->id]);
    Friendship::query()->create(['user_id' => $b->id, 'friend_id' => $a->id]);
}

it('creates an expense whose participant shares sum to the total (equal)', function () {
    $owner = User::factory()->create();
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    friendsForExpense($owner, $alice);
    friendsForExpense($owner, $bob);
    $group = $this->create->execute($owner, 'Bali', [$alice->id, $bob->id]);

    $expense = $this->action->execute(
        group: $group,
        payer: $owner,
        description: 'Nasi Padang',
        totalAmount: 90_001,
        method: SplitMethod::Equal,
        expenseDate: today(),
        participantIds: [$owner->id, $alice->id, $bob->id],
    );

    expect($expense->participants->sum('share_amount'))->toBe(90_001)
        ->and($expense->participants->count())->toBe(3);
});

it('handles a payer who also participates (equal split)', function () {
    $owner = User::factory()->create();
    $alice = User::factory()->create();
    friendsForExpense($owner, $alice);
    $group = $this->create->execute($owner, 'Bali', [$alice->id]);

    $expense = $this->action->execute(
        group: $group,
        payer: $owner,
        description: 'Patungan bensin',
        totalAmount: 100_000,
        method: SplitMethod::Equal,
        expenseDate: today(),
        participantIds: [$owner->id, $alice->id],
    );

    expect($expense->participants->sum('share_amount'))->toBe(100_000)
        ->and($expense->participants->pluck('share_amount')->all())
        ->toEqual([50_000, 50_000]);
});

it('records exact-method shares verbatim', function () {
    $owner = User::factory()->create();
    $alice = User::factory()->create();
    friendsForExpense($owner, $alice);
    $group = $this->create->execute($owner, 'Bali', [$alice->id]);

    $expense = $this->action->execute(
        group: $group,
        payer: $owner,
        description: 'Sewa villa',
        totalAmount: 500_000,
        method: SplitMethod::Exact,
        expenseDate: today(),
        participantIds: [$owner->id, $alice->id],
        shareInputs: [$owner->id => 300_000, $alice->id => 200_000],
    );

    expect($expense->participants->sum('share_amount'))->toBe(500_000);
});

it('records percent-method shares with rounding', function () {
    $owner = User::factory()->create();
    $alice = User::factory()->create();
    friendsForExpense($owner, $alice);
    $group = $this->create->execute($owner, 'Bali', [$alice->id]);

    $expense = $this->action->execute(
        group: $group,
        payer: $owner,
        description: 'Tiket',
        totalAmount: 100_000,
        method: SplitMethod::Percent,
        expenseDate: today(),
        participantIds: [$owner->id, $alice->id],
        shareInputs: [$owner->id => 60, $alice->id => 40],
    );

    expect($expense->participants->sum('share_amount'))->toBe(100_000);
});

it('rejects when payer is not a group member', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $group = $this->create->execute($owner, 'Bali');

    expect(fn () => $this->action->execute(
        group: $group,
        payer: $stranger,
        description: 'X',
        totalAmount: 10_000,
        method: SplitMethod::Equal,
        expenseDate: today(),
        participantIds: [$owner->id],
    ))->toThrow(ExpenseException::class);
});

it('rejects when a participant is not a group member', function () {
    $owner = User::factory()->create();
    $alice = User::factory()->create();
    $stranger = User::factory()->create();
    friendsForExpense($owner, $alice);
    $group = $this->create->execute($owner, 'Bali', [$alice->id]);

    expect(fn () => $this->action->execute(
        group: $group,
        payer: $owner,
        description: 'X',
        totalAmount: 10_000,
        method: SplitMethod::Equal,
        expenseDate: today(),
        participantIds: [$owner->id, $alice->id, $stranger->id],
    ))->toThrow(ExpenseException::class);
});
