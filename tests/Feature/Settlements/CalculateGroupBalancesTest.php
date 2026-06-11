<?php

declare(strict_types=1);

use App\Actions\Expenses\AddExpense;
use App\Actions\Groups\CreateGroup;
use App\Actions\Settlements\CalculateGroupBalances;
use App\Actions\Settlements\RecordSettlement;
use App\Enums\SplitMethod;
use App\Models\Friendship;
use App\Models\User;

beforeEach(function () {
    $this->createGroup = app(CreateGroup::class);
    $this->addExpense = app(AddExpense::class);
    $this->recordSettlement = app(RecordSettlement::class);
    $this->balances = app(CalculateGroupBalances::class);
});

function friendsForBalances(User $a, User $b): void
{
    Friendship::query()->create(['user_id' => $a->id, 'friend_id' => $b->id]);
    Friendship::query()->create(['user_id' => $b->id, 'friend_id' => $a->id]);
}

it('reports zero balance for a member of an empty group', function () {
    $owner = User::factory()->create();
    $group = $this->createGroup->execute($owner, 'Empty');

    expect($this->balances->execute($group))->toBe([$owner->id => 0]);
});

it('sums all balances to zero for any combination of expenses', function () {
    $owner = User::factory()->create();
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    friendsForBalances($owner, $alice);
    friendsForBalances($owner, $bob);
    $group = $this->createGroup->execute($owner, 'Bali', [$alice->id, $bob->id]);

    $this->addExpense->execute(
        group: $group,
        payer: $owner,
        description: 'Villa',
        totalAmount: 90_001,
        method: SplitMethod::Equal,
        expenseDate: today(),
        participantIds: [$owner->id, $alice->id, $bob->id],
    );

    $this->addExpense->execute(
        group: $group,
        payer: $alice,
        description: 'Nasi padang',
        totalAmount: 60_000,
        method: SplitMethod::Equal,
        expenseDate: today(),
        participantIds: [$owner->id, $alice->id, $bob->id],
    );

    expect(array_sum($this->balances->execute($group)))->toBe(0);
});

it('matches a hand-computed three-person example', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $charlie = User::factory()->create();
    friendsForBalances($alice, $bob);
    friendsForBalances($alice, $charlie);
    $group = $this->createGroup->execute($alice, 'Bali', [$bob->id, $charlie->id]);

    // 90k / 3 = 30k each. Alice paid 90k → net +60k. Bob/Charlie each owe 30k.
    $this->addExpense->execute(
        group: $group,
        payer: $alice,
        description: 'Bensin',
        totalAmount: 90_000,
        method: SplitMethod::Equal,
        expenseDate: today(),
        participantIds: [$alice->id, $bob->id, $charlie->id],
    );

    expect($this->balances->execute($group))->toBe([
        $alice->id => 60_000,
        $bob->id => -30_000,
        $charlie->id => -30_000,
    ]);
});

it('moves balances toward zero in the right direction after a settlement', function () {
    $owner = User::factory()->create();
    $alice = User::factory()->create();
    friendsForBalances($owner, $alice);
    $group = $this->createGroup->execute($owner, 'Bali', [$alice->id]);

    $this->addExpense->execute(
        group: $group,
        payer: $owner,
        description: 'Bensin',
        totalAmount: 100_000,
        method: SplitMethod::Equal,
        expenseDate: today(),
        participantIds: [$owner->id, $alice->id],
    );

    expect($this->balances->execute($group))->toBe([
        $owner->id => 50_000,
        $alice->id => -50_000,
    ]);

    $this->recordSettlement->execute(
        actor: $alice,
        group: $group,
        from: $alice,
        to: $owner,
        amount: 20_000,
    );

    expect($this->balances->execute($group))->toBe([
        $owner->id => 30_000,
        $alice->id => -30_000,
    ]);
});

it('reaches zero balances when a debt is fully settled', function () {
    $owner = User::factory()->create();
    $alice = User::factory()->create();
    friendsForBalances($owner, $alice);
    $group = $this->createGroup->execute($owner, 'Bali', [$alice->id]);

    $this->addExpense->execute(
        group: $group,
        payer: $owner,
        description: 'Bensin',
        totalAmount: 100_000,
        method: SplitMethod::Equal,
        expenseDate: today(),
        participantIds: [$owner->id, $alice->id],
    );

    $this->recordSettlement->execute(
        actor: $alice,
        group: $group,
        from: $alice,
        to: $owner,
        amount: 50_000,
    );

    expect($this->balances->execute($group))->toBe([
        $owner->id => 0,
        $alice->id => 0,
    ]);
});

it('conserves money even when expense totals are not evenly divisible', function () {
    $owner = User::factory()->create();
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    friendsForBalances($owner, $alice);
    friendsForBalances($owner, $bob);
    $group = $this->createGroup->execute($owner, 'Trip', [$alice->id, $bob->id]);

    // 100 / 3 = 33 r1. Shares: 34/33/33. Sum back to 100. Owner pays 100.
    $this->addExpense->execute(
        group: $group,
        payer: $owner,
        description: 'Recehan',
        totalAmount: 100,
        method: SplitMethod::Equal,
        expenseDate: today(),
        participantIds: [$owner->id, $alice->id, $bob->id],
    );

    $balances = $this->balances->execute($group);

    expect(array_sum($balances))->toBe(0)
        ->and($balances)->toHaveCount(3);
});

it('treats a reverse settlement as compensating the original one', function () {
    $owner = User::factory()->create();
    $alice = User::factory()->create();
    friendsForBalances($owner, $alice);
    $group = $this->createGroup->execute($owner, 'Trip', [$alice->id]);

    $this->addExpense->execute(
        group: $group,
        payer: $owner,
        description: 'Bensin',
        totalAmount: 100_000,
        method: SplitMethod::Equal,
        expenseDate: today(),
        participantIds: [$owner->id, $alice->id],
    );

    // Alice paid owner 50k (full settlement).
    $this->recordSettlement->execute($alice, $group, $alice, $owner, 50_000);

    // Mistake: owner records reverse settlement to undo (BL-006 long-term fix).
    $this->recordSettlement->execute($owner, $group, $owner, $alice, 50_000);

    // Back to original 50k debt from alice → owner.
    expect($this->balances->execute($group))->toBe([
        $owner->id => 50_000,
        $alice->id => -50_000,
    ]);
});
