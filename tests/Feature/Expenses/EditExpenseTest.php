<?php

declare(strict_types=1);

use App\Actions\Expenses\AddExpense;
use App\Actions\Expenses\EditExpense;
use App\Actions\Groups\CreateGroup;
use App\Enums\SplitMethod;
use App\Exceptions\ExpenseException;
use App\Models\Friendship;
use App\Models\User;

beforeEach(function () {
    $this->edit = app(EditExpense::class);
    $this->add = app(AddExpense::class);
    $this->create = app(CreateGroup::class);
});

function makeFriendsForEdit(User $a, User $b): void
{
    Friendship::query()->create(['user_id' => $a->id, 'friend_id' => $b->id]);
    Friendship::query()->create(['user_id' => $b->id, 'friend_id' => $a->id]);
}

it('recomputes shares when description, total, and method change', function () {
    $owner = User::factory()->create();
    $alice = User::factory()->create();
    makeFriendsForEdit($owner, $alice);
    $group = $this->create->execute($owner, 'Bali', [$alice->id]);

    $expense = $this->add->execute(
        group: $group,
        payer: $owner,
        description: 'Old',
        totalAmount: 100_000,
        method: SplitMethod::Equal,
        expenseDate: today(),
        participantIds: [$owner->id, $alice->id],
    );

    $updated = $this->edit->execute(
        actor: $owner,
        expense: $expense,
        payer: $owner,
        description: 'New',
        totalAmount: 200_000,
        method: SplitMethod::Exact,
        expenseDate: today(),
        participantIds: [$owner->id, $alice->id],
        shareInputs: [$owner->id => 150_000, $alice->id => 50_000],
    );

    expect($updated->description)->toBe('New')
        ->and($updated->total_amount)->toBe(200_000)
        ->and($updated->participants->sum('share_amount'))->toBe(200_000)
        ->and($updated->participants->count())->toBe(2);
});

it('replaces the participant set, not appends', function () {
    $owner = User::factory()->create();
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    makeFriendsForEdit($owner, $alice);
    makeFriendsForEdit($owner, $bob);
    $group = $this->create->execute($owner, 'Bali', [$alice->id, $bob->id]);

    $expense = $this->add->execute(
        group: $group,
        payer: $owner,
        description: 'X',
        totalAmount: 90_000,
        method: SplitMethod::Equal,
        expenseDate: today(),
        participantIds: [$owner->id, $alice->id, $bob->id],
    );

    $updated = $this->edit->execute(
        actor: $owner,
        expense: $expense,
        payer: $owner,
        description: 'X',
        totalAmount: 60_000,
        method: SplitMethod::Equal,
        expenseDate: today(),
        participantIds: [$owner->id, $alice->id],
    );

    expect($updated->participants->count())->toBe(2)
        ->and($updated->participants->pluck('user_id')->all())
        ->toEqualCanonicalizing([$owner->id, $alice->id]);
});

it('lets the group owner edit even if not the original payer', function () {
    $owner = User::factory()->create();
    $alice = User::factory()->create();
    makeFriendsForEdit($owner, $alice);
    $group = $this->create->execute($owner, 'Bali', [$alice->id]);

    $expense = $this->add->execute(
        group: $group,
        payer: $alice,
        description: 'Alice paid',
        totalAmount: 100_000,
        method: SplitMethod::Equal,
        expenseDate: today(),
        participantIds: [$owner->id, $alice->id],
    );

    $updated = $this->edit->execute(
        actor: $owner,
        expense: $expense,
        payer: $alice,
        description: 'Owner edited',
        totalAmount: 80_000,
        method: SplitMethod::Equal,
        expenseDate: today(),
        participantIds: [$owner->id, $alice->id],
    );

    expect($updated->description)->toBe('Owner edited')
        ->and($updated->total_amount)->toBe(80_000);
});

it('blocks members who are neither payer nor owner', function () {
    $owner = User::factory()->create();
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    makeFriendsForEdit($owner, $alice);
    makeFriendsForEdit($owner, $bob);
    $group = $this->create->execute($owner, 'Bali', [$alice->id, $bob->id]);

    $expense = $this->add->execute(
        group: $group,
        payer: $alice,
        description: 'Alice paid',
        totalAmount: 100_000,
        method: SplitMethod::Equal,
        expenseDate: today(),
        participantIds: [$alice->id, $bob->id],
    );

    expect(fn () => $this->edit->execute(
        actor: $bob,
        expense: $expense,
        payer: $alice,
        description: 'Hack',
        totalAmount: 50_000,
        method: SplitMethod::Equal,
        expenseDate: today(),
        participantIds: [$alice->id, $bob->id],
    ))->toThrow(ExpenseException::class);
});
