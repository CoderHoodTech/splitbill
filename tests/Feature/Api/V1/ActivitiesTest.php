<?php

declare(strict_types=1);

use App\Actions\Expenses\AddExpense;
use App\Actions\Groups\CreateGroup;
use App\Enums\SplitMethod;
use App\Models\Friendship;
use App\Models\User;

function makeFriendshipApi(User $a, User $b): void
{
    Friendship::query()->create(['user_id' => $a->id, 'friend_id' => $b->id]);
    Friendship::query()->create(['user_id' => $b->id, 'friend_id' => $a->id]);
}

it('lists activities for a group member', function () {
    $owner = User::factory()->create();
    $alice = User::factory()->create();
    makeFriendshipApi($owner, $alice);
    $group = app(CreateGroup::class)->execute($owner, 'Bali', [$alice->id]);

    app(AddExpense::class)->execute(
        group: $group,
        payer: $owner,
        description: 'Tiket pesawat',
        totalAmount: 1_500_000,
        method: SplitMethod::Equal,
        expenseDate: today(),
        participantIds: [$owner->id, $alice->id],
    );

    $token = $alice->createToken('test')->plainTextToken;

    actingAsToken($token)
        ->getJson("/api/v1/groups/{$group->id}/activities")
        ->assertOk()
        ->assertJsonStructure(['data' => [['id', 'group_id', 'actor_id', 'verb', 'payload', 'created_at']]])
        ->assertJsonPath('data.0.verb', 'expense.created');
});

it('forbids non-members from listing a group activity feed', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $group = app(CreateGroup::class)->execute($owner, 'Bali');

    $token = $stranger->createToken('test')->plainTextToken;

    actingAsToken($token)
        ->getJson("/api/v1/groups/{$group->id}/activities")
        ->assertForbidden();
});

it('requires authentication for activities listing', function () {
    $owner = User::factory()->create();
    $group = app(CreateGroup::class)->execute($owner, 'Bali');

    $this->getJson("/api/v1/groups/{$group->id}/activities")->assertUnauthorized();
});
