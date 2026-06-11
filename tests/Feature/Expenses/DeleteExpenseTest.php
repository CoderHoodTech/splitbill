<?php

declare(strict_types=1);

use App\Actions\Expenses\AddExpense;
use App\Actions\Expenses\DeleteExpense;
use App\Actions\Groups\CreateGroup;
use App\Enums\SplitMethod;
use App\Exceptions\ExpenseException;
use App\Models\ExpenseParticipant;
use App\Models\Friendship;
use App\Models\User;

beforeEach(function () {
    $this->delete = app(DeleteExpense::class);
    $this->add = app(AddExpense::class);
    $this->create = app(CreateGroup::class);
});

function makeFriendsForDelete(User $a, User $b): void
{
    Friendship::query()->create(['user_id' => $a->id, 'friend_id' => $b->id]);
    Friendship::query()->create(['user_id' => $b->id, 'friend_id' => $a->id]);
}

it('hard-deletes the expense and cascades participants', function () {
    $owner = User::factory()->create();
    $alice = User::factory()->create();
    makeFriendsForDelete($owner, $alice);
    $group = $this->create->execute($owner, 'Bali', [$alice->id]);

    $expense = $this->add->execute(
        group: $group,
        payer: $owner,
        description: 'X',
        totalAmount: 100_000,
        method: SplitMethod::Equal,
        expenseDate: today(),
        participantIds: [$owner->id, $alice->id],
    );

    $expenseId = $expense->id;
    $this->delete->execute($owner, $expense);

    expect($expense->fresh())->toBeNull()
        ->and(ExpenseParticipant::query()->where('expense_id', $expenseId)->exists())->toBeFalse();
});

it('lets the group owner delete an expense paid by someone else', function () {
    $owner = User::factory()->create();
    $alice = User::factory()->create();
    makeFriendsForDelete($owner, $alice);
    $group = $this->create->execute($owner, 'Bali', [$alice->id]);

    $expense = $this->add->execute(
        group: $group,
        payer: $alice,
        description: 'X',
        totalAmount: 100_000,
        method: SplitMethod::Equal,
        expenseDate: today(),
        participantIds: [$owner->id, $alice->id],
    );

    $this->delete->execute($owner, $expense);

    expect($expense->fresh())->toBeNull();
});

it('blocks members who are neither payer nor owner from deleting', function () {
    $owner = User::factory()->create();
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    makeFriendsForDelete($owner, $alice);
    makeFriendsForDelete($owner, $bob);
    $group = $this->create->execute($owner, 'Bali', [$alice->id, $bob->id]);

    $expense = $this->add->execute(
        group: $group,
        payer: $alice,
        description: 'X',
        totalAmount: 90_000,
        method: SplitMethod::Equal,
        expenseDate: today(),
        participantIds: [$alice->id, $bob->id, $owner->id],
    );

    expect(fn () => $this->delete->execute($bob, $expense))
        ->toThrow(ExpenseException::class);

    expect($expense->fresh())->not->toBeNull();
});
