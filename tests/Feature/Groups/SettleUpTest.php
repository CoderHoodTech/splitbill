<?php

declare(strict_types=1);

use App\Actions\Expenses\AddExpense;
use App\Actions\Groups\CreateGroup;
use App\Enums\SplitMethod;
use App\Livewire\Groups\SettleUp;
use App\Livewire\Groups\ShowGroup;
use App\Models\Friendship;
use App\Models\Settlement;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->createGroup = app(CreateGroup::class);
    $this->addExpense = app(AddExpense::class);
});

function friendsForSettleUp(User $a, User $b): void
{
    Friendship::query()->create(['user_id' => $a->id, 'friend_id' => $b->id]);
    Friendship::query()->create(['user_id' => $b->id, 'friend_id' => $a->id]);
}

test('balances panel shows "all settled" when no expenses exist', function () {
    $owner = User::factory()->create();
    $group = $this->createGroup->execute($owner, 'Solo');

    Livewire::actingAs($owner)
        ->test(ShowGroup::class, ['group' => $group])
        ->assertSee('All settled up');
});

test('balances panel shows who pays whom after an expense', function () {
    $owner = User::factory()->create();
    $alice = User::factory()->create();
    friendsForSettleUp($owner, $alice);
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

    Livewire::actingAs($alice)
        ->test(ShowGroup::class, ['group' => $group])
        ->assertSee('Rp50.000');
});

test('recording a settlement creates a row and updates balances', function () {
    $owner = User::factory()->create();
    $alice = User::factory()->create();
    friendsForSettleUp($owner, $alice);
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

    Livewire::actingAs($alice)
        ->test(SettleUp::class, ['group' => $group])
        ->set('from_id', $alice->id)
        ->set('to_id', $owner->id)
        ->set('amount', 50_000)
        ->call('save')
        ->assertHasNoErrors();

    expect(Settlement::query()->count())->toBe(1)
        ->and((int) Settlement::query()->first()->amount)->toBe(50_000);

    Livewire::actingAs($alice)
        ->test(ShowGroup::class, ['group' => $group])
        ->assertSee('All settled up');
});

test('a partial settlement leaves a remaining balance', function () {
    $owner = User::factory()->create();
    $alice = User::factory()->create();
    friendsForSettleUp($owner, $alice);
    $group = $this->createGroup->execute($owner, 'Bali', [$alice->id]);

    $this->addExpense->execute(
        group: $group,
        payer: $owner,
        description: 'X',
        totalAmount: 100_000,
        method: SplitMethod::Equal,
        expenseDate: today(),
        participantIds: [$owner->id, $alice->id],
    );

    Livewire::actingAs($alice)
        ->test(SettleUp::class, ['group' => $group])
        ->set('from_id', $alice->id)
        ->set('to_id', $owner->id)
        ->set('amount', 20_000)
        ->call('save')
        ->assertHasNoErrors();

    Livewire::actingAs($alice)
        ->test(ShowGroup::class, ['group' => $group])
        ->assertSee('Rp30.000');
});

test('a non-member cannot mount the settle-up component', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $group = $this->createGroup->execute($owner, 'Bali');

    Livewire::actingAs($stranger)
        ->test(SettleUp::class, ['group' => $group])
        ->assertStatus(403);
});

test('the actor must be involved in the settlement (validation surfaces error)', function () {
    $owner = User::factory()->create();
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    friendsForSettleUp($owner, $alice);
    friendsForSettleUp($owner, $bob);
    $group = $this->createGroup->execute($owner, 'Bali', [$alice->id, $bob->id]);

    // Bob tries to record a settlement between owner and alice — should error.
    Livewire::actingAs($bob)
        ->test(SettleUp::class, ['group' => $group])
        ->set('from_id', $alice->id)
        ->set('to_id', $owner->id)
        ->set('amount', 10_000)
        ->call('save')
        ->assertHasErrors('amount');

    expect(Settlement::query()->count())->toBe(0);
});
