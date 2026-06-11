<?php

declare(strict_types=1);

use App\Actions\Expenses\AddExpense;
use App\Actions\Groups\CreateGroup;
use App\Enums\SplitMethod;
use App\Livewire\Activity\GroupActivity;
use App\Models\Friendship;
use App\Models\User;
use Livewire\Livewire;

function makeFriendshipLW(User $a, User $b): void
{
    Friendship::query()->create(['user_id' => $a->id, 'friend_id' => $b->id]);
    Friendship::query()->create(['user_id' => $b->id, 'friend_id' => $a->id]);
}

it('shows recorded activity to a group member', function () {
    $owner = User::factory()->create();
    $alice = User::factory()->create();
    makeFriendshipLW($owner, $alice);
    $group = app(CreateGroup::class)->execute($owner, 'Bali', [$alice->id]);

    app(AddExpense::class)->execute(
        group: $group,
        payer: $owner,
        description: 'Sushi malam',
        totalAmount: 80_000,
        method: SplitMethod::Equal,
        expenseDate: today(),
        participantIds: [$owner->id, $alice->id],
    );

    Livewire::actingAs($alice)
        ->test(GroupActivity::class, ['group' => $group])
        ->assertSeeText('Sushi malam')
        ->assertSeeText($owner->name);
});

it('blocks non-members from viewing a group activity feed', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $group = app(CreateGroup::class)->execute($owner, 'Bali');

    Livewire::actingAs($stranger)
        ->test(GroupActivity::class, ['group' => $group])
        ->assertStatus(403);
});

it('shows empty state when the group has no activity', function () {
    $owner = User::factory()->create();
    $group = app(CreateGroup::class)->execute($owner, 'Bali');

    Livewire::actingAs($owner)
        ->test(GroupActivity::class, ['group' => $group])
        ->assertSeeText(__('No activity yet.'));
});
