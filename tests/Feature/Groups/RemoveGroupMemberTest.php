<?php

declare(strict_types=1);

use App\Actions\Groups\CreateGroup;
use App\Actions\Groups\RemoveGroupMember;
use App\Exceptions\GroupException;
use App\Models\Friendship;
use App\Models\User;

beforeEach(function () {
    $this->action = app(RemoveGroupMember::class);
    $this->create = app(CreateGroup::class);
});

function makeFriendship(User $a, User $b): void
{
    Friendship::query()->create(['user_id' => $a->id, 'friend_id' => $b->id]);
    Friendship::query()->create(['user_id' => $b->id, 'friend_id' => $a->id]);
}

it('lets the owner remove a member', function () {
    $owner = User::factory()->create();
    $alice = User::factory()->create();
    makeFriendship($owner, $alice);
    $group = $this->create->execute($owner, 'Trip', [$alice->id]);

    $this->action->execute($owner, $group, $alice);

    expect($group->fresh()->hasMember($alice))->toBeFalse();
});

it('refuses to remove the owner', function () {
    $owner = User::factory()->create();
    $group = $this->create->execute($owner, 'Trip');

    expect(fn () => $this->action->execute($owner, $group, $owner))
        ->toThrow(GroupException::class);
});

it('blocks non-owners from removing members', function () {
    $owner = User::factory()->create();
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    makeFriendship($owner, $alice);
    makeFriendship($owner, $bob);
    $group = $this->create->execute($owner, 'Trip', [$alice->id, $bob->id]);

    expect(fn () => $this->action->execute($alice, $group, $bob))
        ->toThrow(GroupException::class);
});

it('errors when the user is not a member', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $group = $this->create->execute($owner, 'Trip');

    expect(fn () => $this->action->execute($owner, $group, $stranger))
        ->toThrow(GroupException::class);
});
