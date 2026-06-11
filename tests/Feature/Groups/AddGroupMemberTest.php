<?php

declare(strict_types=1);

use App\Actions\Groups\AddGroupMember;
use App\Actions\Groups\CreateGroup;
use App\Exceptions\GroupException;
use App\Models\Friendship;
use App\Models\User;

beforeEach(function () {
    $this->action = app(AddGroupMember::class);
    $this->create = app(CreateGroup::class);
});

function befriend(User $a, User $b): void
{
    Friendship::query()->create(['user_id' => $a->id, 'friend_id' => $b->id]);
    Friendship::query()->create(['user_id' => $b->id, 'friend_id' => $a->id]);
}

it('lets the owner add a friend to the group', function () {
    $owner = User::factory()->create();
    $alice = User::factory()->create();
    befriend($owner, $alice);
    $group = $this->create->execute($owner, 'Trip');

    $this->action->execute($owner, $group, $alice);

    expect($group->fresh()->hasMember($alice))->toBeTrue();
});

it('blocks non-owners from adding members', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $alice = User::factory()->create();
    befriend($intruder, $alice);
    $group = $this->create->execute($owner, 'Trip');

    expect(fn () => $this->action->execute($intruder, $group, $alice))
        ->toThrow(GroupException::class);
});

it('blocks adding a non-friend even if the owner is acting', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $group = $this->create->execute($owner, 'Trip');

    expect(fn () => $this->action->execute($owner, $group, $stranger))
        ->toThrow(GroupException::class);
});

it('throws when the same person is added twice', function () {
    $owner = User::factory()->create();
    $alice = User::factory()->create();
    befriend($owner, $alice);
    $group = $this->create->execute($owner, 'Trip', [$alice->id]);

    expect(fn () => $this->action->execute($owner, $group, $alice))
        ->toThrow(GroupException::class);
});
