<?php

declare(strict_types=1);

use App\Models\FriendRequest;
use App\Models\User;
use App\Notifications\FriendRequestReceived;

it('lists notifications for the authenticated user', function () {
    $user = User::factory()->create();
    $sender = User::factory()->create();
    $request = FriendRequest::factory()->create([
        'sender_id' => $sender->id,
        'receiver_id' => $user->id,
    ]);
    $user->notify(new FriendRequestReceived($request, $sender));

    $token = $user->createToken('test')->plainTextToken;

    actingAsToken($token)
        ->getJson('/api/v1/notifications')
        ->assertOk()
        ->assertJsonStructure(['data' => [['id', 'type', 'data', 'read_at', 'created_at']]])
        ->assertJsonPath('data.0.type', 'friend_request.received');
});

it('returns the unread count', function () {
    $user = User::factory()->create();
    $sender = User::factory()->create();
    $request = FriendRequest::factory()->create([
        'sender_id' => $sender->id,
        'receiver_id' => $user->id,
    ]);
    $user->notify(new FriendRequestReceived($request, $sender));
    $user->notify(new FriendRequestReceived($request, $sender));

    $token = $user->createToken('test')->plainTextToken;

    actingAsToken($token)
        ->getJson('/api/v1/notifications/unread-count')
        ->assertOk()
        ->assertExactJson(['unread_count' => 2]);
});

it('marks a notification as read via the API', function () {
    $user = User::factory()->create();
    $sender = User::factory()->create();
    $request = FriendRequest::factory()->create([
        'sender_id' => $sender->id,
        'receiver_id' => $user->id,
    ]);
    $user->notify(new FriendRequestReceived($request, $sender));
    $notification = $user->notifications()->first();

    $token = $user->createToken('test')->plainTextToken;

    actingAsToken($token)
        ->postJson("/api/v1/notifications/{$notification->id}/read")
        ->assertNoContent();

    expect($notification->fresh()->read_at)->not->toBeNull();
});

it('marks all notifications as read via the API', function () {
    $user = User::factory()->create();
    $sender = User::factory()->create();
    $request = FriendRequest::factory()->create([
        'sender_id' => $sender->id,
        'receiver_id' => $user->id,
    ]);
    $user->notify(new FriendRequestReceived($request, $sender));
    $user->notify(new FriendRequestReceived($request, $sender));

    $token = $user->createToken('test')->plainTextToken;

    actingAsToken($token)
        ->postJson('/api/v1/notifications/read-all')
        ->assertNoContent();

    expect($user->unreadNotifications()->count())->toBe(0);
});

it('refuses to mark another user notification as read', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $sender = User::factory()->create();
    $request = FriendRequest::factory()->create([
        'sender_id' => $sender->id,
        'receiver_id' => $userB->id,
    ]);
    $userB->notify(new FriendRequestReceived($request, $sender));
    $notification = $userB->notifications()->first();

    $token = $userA->createToken('test')->plainTextToken;

    actingAsToken($token)
        ->postJson("/api/v1/notifications/{$notification->id}/read")
        ->assertNotFound();

    expect($notification->fresh()->read_at)->toBeNull();
});

it('requires authentication for notification endpoints', function () {
    $this->getJson('/api/v1/notifications')->assertUnauthorized();
    $this->getJson('/api/v1/notifications/unread-count')->assertUnauthorized();
    $this->postJson('/api/v1/notifications/read-all')->assertUnauthorized();
});
