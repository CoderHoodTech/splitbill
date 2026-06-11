<?php

declare(strict_types=1);

use App\Livewire\Groups\CreateGroup;
use App\Livewire\Groups\GroupList;
use App\Models\Friendship;
use App\Models\Group;
use App\Models\User;
use Livewire\Livewire;

test('the groups index requires authentication', function () {
    $this->get(route('groups.index'))->assertRedirect(route('login'));
});

test('the groups index renders GroupList', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('groups.index'))
        ->assertOk()
        ->assertSeeLivewire(GroupList::class);
});

test('GroupList shows only groups the user is a member of', function () {
    $me = User::factory()->create();
    $other = User::factory()->create();

    // Group I belong to
    $mine = Group::factory()->create(['name' => 'My Trip', 'owner_id' => $me->id]);
    $mine->groupMembers()->create(['user_id' => $me->id, 'role' => 'owner']);

    // Group I do NOT belong to
    $stranger = Group::factory()->create(['name' => 'Stranger Trip', 'owner_id' => $other->id]);
    $stranger->groupMembers()->create(['user_id' => $other->id, 'role' => 'owner']);

    Livewire::actingAs($me)
        ->test(GroupList::class)
        ->assertSee('My Trip')
        ->assertDontSee('Stranger Trip');
});

test('CreateGroup persists a new group with selected friends', function () {
    $me = User::factory()->create();
    $alice = User::factory()->create();
    Friendship::query()->create(['user_id' => $me->id, 'friend_id' => $alice->id]);
    Friendship::query()->create(['user_id' => $alice->id, 'friend_id' => $me->id]);

    Livewire::actingAs($me)
        ->test(CreateGroup::class)
        ->set('name', 'Bali 2026')
        ->set('selectedFriendIds', [$alice->id])
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('group-created');

    $group = Group::query()->where('name', 'Bali 2026')->firstOrFail();

    expect($group->owner_id)->toBe($me->id)
        ->and($group->groupMembers()->count())->toBe(2)
        ->and($group->hasMember($alice))->toBeTrue();
});

test('CreateGroup shows an error on empty name', function () {
    $me = User::factory()->create();

    Livewire::actingAs($me)
        ->test(CreateGroup::class)
        ->set('name', '')
        ->call('save')
        ->assertHasErrors('name');
});
