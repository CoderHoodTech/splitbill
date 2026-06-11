<?php

declare(strict_types=1);

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;

// `befriend()` is already defined in tests/Feature/Groups/AddGroupMemberTest.php
// (top-level Pest function declarations are global).

it('lists groups the authenticated user belongs to', function () {
    $user = User::factory()->create();
    $group = Group::factory()->create(['owner_id' => $user->id]);
    GroupMember::factory()->owner()->create(['group_id' => $group->id, 'user_id' => $user->id]);

    $other = Group::factory()->create();

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/groups');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $group->id)
        ->assertJsonMissing(['id' => $other->id]);
});

it('creates a group with friend members', function () {
    $owner = User::factory()->create();
    $friend = User::factory()->create();
    befriend($owner, $friend);

    $response = $this->actingAs($owner, 'sanctum')->postJson('/api/v1/groups', [
        'name' => 'Trip Bali',
        'description' => 'Liburan akhir tahun',
        'member_ids' => [$friend->id],
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Trip Bali')
        ->assertJsonPath('data.owner_id', $owner->id);

    $groupId = $response->json('data.id');

    expect(GroupMember::query()->where('group_id', $groupId)->count())->toBe(2);
});

it('returns 422 when adding a non-friend at create time', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();

    $response = $this->actingAs($owner, 'sanctum')->postJson('/api/v1/groups', [
        'name' => 'Lunch',
        'member_ids' => [$stranger->id],
    ]);

    $response->assertUnprocessable();
});

it('shows a group to members and forbids non-members', function () {
    $owner = User::factory()->create();
    $group = Group::factory()->create(['owner_id' => $owner->id]);
    GroupMember::factory()->owner()->create(['group_id' => $group->id, 'user_id' => $owner->id]);
    $outsider = User::factory()->create();

    $this->actingAs($owner, 'sanctum')->getJson("/api/v1/groups/{$group->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $group->id);

    $this->actingAs($outsider, 'sanctum')->getJson("/api/v1/groups/{$group->id}")
        ->assertForbidden();
});

it('adds a friend as a member (owner only)', function () {
    $owner = User::factory()->create();
    $friend = User::factory()->create();
    befriend($owner, $friend);

    $group = Group::factory()->create(['owner_id' => $owner->id]);
    GroupMember::factory()->owner()->create(['group_id' => $group->id, 'user_id' => $owner->id]);

    $response = $this->actingAs($owner, 'sanctum')->postJson("/api/v1/groups/{$group->id}/members", [
        'user_id' => $friend->id,
    ]);

    $response->assertCreated();

    expect($group->fresh()->hasMember($friend))->toBeTrue();
});

it('forbids non-owners from adding members', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $friend = User::factory()->create();
    befriend($member, $friend);

    $group = Group::factory()->create(['owner_id' => $owner->id]);
    GroupMember::factory()->owner()->create(['group_id' => $group->id, 'user_id' => $owner->id]);
    GroupMember::factory()->create(['group_id' => $group->id, 'user_id' => $member->id]);

    $this->actingAs($member, 'sanctum')
        ->postJson("/api/v1/groups/{$group->id}/members", ['user_id' => $friend->id])
        ->assertForbidden();
});

it('removes a member (owner only)', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();

    $group = Group::factory()->create(['owner_id' => $owner->id]);
    GroupMember::factory()->owner()->create(['group_id' => $group->id, 'user_id' => $owner->id]);
    GroupMember::factory()->create(['group_id' => $group->id, 'user_id' => $member->id]);

    $this->actingAs($owner, 'sanctum')
        ->deleteJson("/api/v1/groups/{$group->id}/members/{$member->id}")
        ->assertNoContent();

    expect($group->fresh()->hasMember($member))->toBeFalse();
});
