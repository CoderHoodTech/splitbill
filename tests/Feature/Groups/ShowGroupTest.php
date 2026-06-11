<?php

declare(strict_types=1);

use App\Actions\Groups\CreateGroup;
use App\Livewire\Groups\ShowGroup;
use App\Models\Friendship;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->create = app(CreateGroup::class);
});

function pairFriends(User $a, User $b): void
{
    Friendship::query()->create(['user_id' => $a->id, 'friend_id' => $b->id]);
    Friendship::query()->create(['user_id' => $b->id, 'friend_id' => $a->id]);
}

test('non-members get 403 when visiting the group page', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $group = $this->create->execute($owner, 'Trip');

    $this->actingAs($intruder)
        ->get(route('groups.show', $group))
        ->assertForbidden();
});

test('members can view the group', function () {
    $owner = User::factory()->create();
    $alice = User::factory()->create();
    pairFriends($owner, $alice);
    $group = $this->create->execute($owner, 'Trip Bali', [$alice->id]);

    $this->actingAs($alice)
        ->get(route('groups.show', $group))
        ->assertOk()
        ->assertSee('Trip Bali');
});

test('owner can add a friend as a new member', function () {
    $owner = User::factory()->create();
    $alice = User::factory()->create();
    pairFriends($owner, $alice);
    $group = $this->create->execute($owner, 'Trip');

    Livewire::actingAs($owner)
        ->test(ShowGroup::class, ['group' => $group])
        ->set('newMemberFriendId', (string) $alice->id)
        ->call('addMember');

    expect($group->fresh()->hasMember($alice))->toBeTrue();
});

test('non-owner cannot add members', function () {
    $owner = User::factory()->create();
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    pairFriends($owner, $alice);
    pairFriends($alice, $bob);
    $group = $this->create->execute($owner, 'Trip', [$alice->id]);

    Livewire::actingAs($alice)
        ->test(ShowGroup::class, ['group' => $group])
        ->set('newMemberFriendId', (string) $bob->id)
        ->call('addMember')
        ->assertStatus(403);
});

test('owner can remove a member', function () {
    $owner = User::factory()->create();
    $alice = User::factory()->create();
    pairFriends($owner, $alice);
    $group = $this->create->execute($owner, 'Trip', [$alice->id]);

    Livewire::actingAs($owner)
        ->test(ShowGroup::class, ['group' => $group])
        ->call('removeMember', $alice->id);

    expect($group->fresh()->hasMember($alice))->toBeFalse();
});

test('non-owner cannot remove members', function () {
    $owner = User::factory()->create();
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    pairFriends($owner, $alice);
    pairFriends($owner, $bob);
    $group = $this->create->execute($owner, 'Trip', [$alice->id, $bob->id]);

    Livewire::actingAs($alice)
        ->test(ShowGroup::class, ['group' => $group])
        ->call('removeMember', $bob->id)
        ->assertStatus(403);
});
