<?php

declare(strict_types=1);

use App\Enums\FriendRequestStatus;
use App\Models\FriendRequest;
use App\Models\Friendship;
use App\Models\User;

it('lists the authenticated user\'s friends', function () {
    $user = User::factory()->create();
    $friend = User::factory()->create(['name' => 'Budi']);
    Friendship::query()->create(['user_id' => $user->id, 'friend_id' => $friend->id]);
    Friendship::query()->create(['user_id' => $friend->id, 'friend_id' => $user->id]);

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/friends');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $friend->id);
});

it('lists pending incoming and sent requests', function () {
    $user = User::factory()->create();
    $incoming = FriendRequest::factory()->create(['receiver_id' => $user->id]);
    $sent = FriendRequest::factory()->create(['sender_id' => $user->id]);
    FriendRequest::factory()->accepted()->create(['receiver_id' => $user->id]);

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/friends/requests');

    $response->assertOk()
        ->assertJsonCount(1, 'incoming')
        ->assertJsonCount(1, 'sent')
        ->assertJsonPath('incoming.0.id', $incoming->id)
        ->assertJsonPath('sent.0.id', $sent->id);
});

it('sends a friend request by code', function () {
    $sender = User::factory()->create();
    $receiver = User::factory()->create();

    $response = $this->actingAs($sender, 'sanctum')->postJson('/api/v1/friends/requests', [
        'friend_code' => $receiver->friend_code,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.sender_id', $sender->id)
        ->assertJsonPath('data.receiver_id', $receiver->id)
        ->assertJsonPath('data.status', FriendRequestStatus::Pending->value);
});

it('returns 422 when the friend code is unknown', function () {
    $sender = User::factory()->create();

    $response = $this->actingAs($sender, 'sanctum')->postJson('/api/v1/friends/requests', [
        'friend_code' => 'NOPENOPE',
    ]);

    $response->assertUnprocessable()->assertJsonPath('message', 'Friend code not found.');
});

it('accepts a pending request and creates the friendship', function () {
    $sender = User::factory()->create();
    $receiver = User::factory()->create();
    $request = FriendRequest::factory()->create([
        'sender_id' => $sender->id,
        'receiver_id' => $receiver->id,
    ]);

    $response = $this->actingAs($receiver, 'sanctum')
        ->postJson("/api/v1/friends/requests/{$request->id}/accept");

    $response->assertOk()
        ->assertJsonPath('data.status', FriendRequestStatus::Accepted->value);

    expect($sender->fresh()->isFriendsWith($receiver))->toBeTrue();
});

it('forbids accepting a request not addressed to you', function () {
    $sender = User::factory()->create();
    $receiver = User::factory()->create();
    $outsider = User::factory()->create();
    $request = FriendRequest::factory()->create([
        'sender_id' => $sender->id,
        'receiver_id' => $receiver->id,
    ]);

    $this->actingAs($outsider, 'sanctum')
        ->postJson("/api/v1/friends/requests/{$request->id}/accept")
        ->assertForbidden();
});

it('declines a pending request', function () {
    $sender = User::factory()->create();
    $receiver = User::factory()->create();
    $request = FriendRequest::factory()->create([
        'sender_id' => $sender->id,
        'receiver_id' => $receiver->id,
    ]);

    $response = $this->actingAs($receiver, 'sanctum')
        ->postJson("/api/v1/friends/requests/{$request->id}/decline");

    $response->assertOk()
        ->assertJsonPath('data.status', FriendRequestStatus::Declined->value);

    expect($sender->fresh()->isFriendsWith($receiver))->toBeFalse();
});

it('rejects unauthenticated friend list access', function () {
    $this->getJson('/api/v1/friends')->assertUnauthorized();
});
