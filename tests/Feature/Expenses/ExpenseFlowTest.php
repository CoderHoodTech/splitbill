<?php

declare(strict_types=1);

use App\Actions\Expenses\AddExpense as AddExpenseAction;
use App\Actions\Groups\CreateGroup;
use App\Enums\SplitMethod;
use App\Livewire\Expenses\AddExpense;
use App\Livewire\Expenses\EditExpense;
use App\Livewire\Groups\ShowGroup;
use App\Models\Expense;
use App\Models\Friendship;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->create = app(CreateGroup::class);
    $this->add = app(AddExpenseAction::class);
});

function makeFriendsForFlow(User $a, User $b): void
{
    Friendship::query()->create(['user_id' => $a->id, 'friend_id' => $b->id]);
    Friendship::query()->create(['user_id' => $b->id, 'friend_id' => $a->id]);
}

test('a member can add an equal-split expense through the Livewire form', function () {
    $owner = User::factory()->create();
    $alice = User::factory()->create();
    makeFriendsForFlow($owner, $alice);
    $group = $this->create->execute($owner, 'Trip', [$alice->id]);

    Livewire::actingAs($owner)
        ->test(AddExpense::class, ['group' => $group])
        ->set('description', 'Sate Padang')
        ->set('total_amount', 100_000)
        ->set('payer_id', $owner->id)
        ->set('split_method', 'equal')
        ->set("participants.{$owner->id}", 1)
        ->set("participants.{$alice->id}", 1)
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('expense-saved');

    $expense = Expense::query()->where('description', 'Sate Padang')->firstOrFail();
    expect($expense->participants->sum('share_amount'))->toBe(100_000)
        ->and($expense->payer_id)->toBe($owner->id);
});

test('a non-member cannot mount the AddExpense form', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $group = $this->create->execute($owner, 'Trip');

    Livewire::actingAs($intruder)
        ->test(AddExpense::class, ['group' => $group])
        ->assertStatus(403);
});

test('the original payer can edit their expense via Livewire', function () {
    $owner = User::factory()->create();
    $alice = User::factory()->create();
    makeFriendsForFlow($owner, $alice);
    $group = $this->create->execute($owner, 'Trip', [$alice->id]);
    $expense = $this->add->execute(
        group: $group,
        payer: $alice,
        description: 'Old',
        totalAmount: 50_000,
        method: SplitMethod::Equal,
        expenseDate: today(),
        participantIds: [$owner->id, $alice->id],
    );

    Livewire::actingAs($alice)
        ->test(EditExpense::class)
        ->call('open', $expense->id)
        ->set('description', 'New desc')
        ->set('total_amount', 80_000)
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('expense-saved');

    expect($expense->fresh()->description)->toBe('New desc')
        ->and($expense->fresh()->total_amount)->toBe(80_000)
        ->and($expense->fresh()->participants->sum('share_amount'))->toBe(80_000);
});

test('a member who is neither payer nor owner cannot edit', function () {
    $owner = User::factory()->create();
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    makeFriendsForFlow($owner, $alice);
    makeFriendsForFlow($owner, $bob);
    $group = $this->create->execute($owner, 'Trip', [$alice->id, $bob->id]);
    $expense = $this->add->execute(
        group: $group,
        payer: $alice,
        description: 'Alice paid',
        totalAmount: 60_000,
        method: SplitMethod::Equal,
        expenseDate: today(),
        participantIds: [$alice->id, $bob->id, $owner->id],
    );

    Livewire::actingAs($bob)
        ->test(EditExpense::class)
        ->call('open', $expense->id)
        ->assertStatus(403);
});

test('the group owner can delete any expense via ShowGroup', function () {
    $owner = User::factory()->create();
    $alice = User::factory()->create();
    makeFriendsForFlow($owner, $alice);
    $group = $this->create->execute($owner, 'Trip', [$alice->id]);
    $expense = $this->add->execute(
        group: $group,
        payer: $alice,
        description: 'X',
        totalAmount: 30_000,
        method: SplitMethod::Equal,
        expenseDate: today(),
        participantIds: [$owner->id, $alice->id],
    );

    Livewire::actingAs($owner)
        ->test(ShowGroup::class, ['group' => $group])
        ->call('deleteExpense', $expense->id);

    expect(Expense::query()->find($expense->id))->toBeNull();
});

test('a non-payer non-owner cannot delete via ShowGroup', function () {
    $owner = User::factory()->create();
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    makeFriendsForFlow($owner, $alice);
    makeFriendsForFlow($owner, $bob);
    $group = $this->create->execute($owner, 'Trip', [$alice->id, $bob->id]);
    $expense = $this->add->execute(
        group: $group,
        payer: $alice,
        description: 'X',
        totalAmount: 30_000,
        method: SplitMethod::Equal,
        expenseDate: today(),
        participantIds: [$alice->id, $bob->id, $owner->id],
    );

    Livewire::actingAs($bob)
        ->test(ShowGroup::class, ['group' => $group])
        ->call('deleteExpense', $expense->id)
        ->assertStatus(403);

    expect(Expense::query()->find($expense->id))->not->toBeNull();
});

test('exact-method validation rejects shares that do not sum to total', function () {
    $owner = User::factory()->create();
    $alice = User::factory()->create();
    makeFriendsForFlow($owner, $alice);
    $group = $this->create->execute($owner, 'Trip', [$alice->id]);

    Livewire::actingAs($owner)
        ->test(AddExpense::class, ['group' => $group])
        ->set('description', 'Tiket pesawat')
        ->set('total_amount', 100_000)
        ->set('payer_id', $owner->id)
        ->set('split_method', 'exact')
        ->set("participants.{$owner->id}", 1)
        ->set("participants.{$alice->id}", 1)
        ->set("shareInputs.{$owner->id}", '30000')
        ->set("shareInputs.{$alice->id}", '50000')
        ->call('save')
        ->assertHasErrors('total_amount');

    expect(Expense::query()->where('description', 'Tiket pesawat')->exists())->toBeFalse();
});
