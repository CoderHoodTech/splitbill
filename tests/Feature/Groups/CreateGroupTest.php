<?php

declare(strict_types=1);

use App\Actions\Groups\CreateGroup;
use App\Enums\GroupMemberRole;
use App\Exceptions\GroupException;
use App\Models\Friendship;
use App\Models\Group;
use App\Models\User;

beforeEach(function () {
    $this->action = app(CreateGroup::class);
});

function makeFriends(User $a, User $b): void
{
    Friendship::query()->create(['user_id' => $a->id, 'friend_id' => $b->id]);
    Friendship::query()->create(['user_id' => $b->id, 'friend_id' => $a->id]);
}

it('creates a group, makes the owner a member, and stores the role', function () {
    $owner = User::factory()->create();

    $group = $this->action->execute($owner, 'Trip Bali');

    expect($group)->toBeInstanceOf(Group::class)
        ->and($group->name)->toBe('Trip Bali')
        ->and($group->owner_id)->toBe($owner->id)
        ->and($group->groupMembers()->count())->toBe(1)
        ->and($group->roleFor($owner))->toBe(GroupMemberRole::Owner);
});

it('adds the listed friends as members', function () {
    $owner = User::factory()->create();
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    makeFriends($owner, $alice);
    makeFriends($owner, $bob);

    $group = $this->action->execute($owner, 'Geng Kantor', [$alice->id, $bob->id]);

    expect($group->groupMembers()->count())->toBe(3)
        ->and($group->hasMember($alice))->toBeTrue()
        ->and($group->hasMember($bob))->toBeTrue()
        ->and($group->roleFor($alice))->toBe(GroupMemberRole::Member);
});

it('rejects adding a non-friend', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();

    expect(fn () => $this->action->execute($owner, 'Trip', [$stranger->id]))
        ->toThrow(GroupException::class);
});

it('rejects an empty group name', function () {
    $owner = User::factory()->create();

    expect(fn () => $this->action->execute($owner, '   '))
        ->toThrow(GroupException::class);
});

it('silently drops the owner from the memberIds list', function () {
    $owner = User::factory()->create();
    $alice = User::factory()->create();
    makeFriends($owner, $alice);

    $group = $this->action->execute($owner, 'Trip', [$owner->id, $alice->id]);

    // Owner only counted once.
    expect($group->groupMembers()->count())->toBe(2);
});
