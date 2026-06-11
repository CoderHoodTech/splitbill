<?php

declare(strict_types=1);

use App\Livewire\Notifications\Bell;
use App\Models\FriendRequest;
use App\Models\User;
use App\Notifications\FriendRequestReceived;
use Livewire\Livewire;

it('renders the bell with the correct unread count for the current user', function () {
    $user = User::factory()->create();
    $sender = User::factory()->create();

    $request = FriendRequest::factory()->create([
        'sender_id' => $sender->id,
        'receiver_id' => $user->id,
    ]);

    $user->notify(new FriendRequestReceived($request, $sender));
    $user->notify(new FriendRequestReceived($request, $sender));

    Livewire::actingAs($user)
        ->test(Bell::class)
        ->assertSet('unreadCount', 2)
        ->assertSeeText('2');
});

it('marks a notification as read when clicked', function () {
    $user = User::factory()->create();
    $sender = User::factory()->create();
    $request = FriendRequest::factory()->create([
        'sender_id' => $sender->id,
        'receiver_id' => $user->id,
    ]);

    $user->notify(new FriendRequestReceived($request, $sender));

    $notification = $user->notifications()->first();

    Livewire::actingAs($user)
        ->test(Bell::class)
        ->assertSet('unreadCount', 1)
        ->call('markAsRead', $notification->id)
        ->assertSet('unreadCount', 0);

    expect($notification->fresh()->read_at)->not->toBeNull();
});

it('marks all notifications as read', function () {
    $user = User::factory()->create();
    $sender = User::factory()->create();
    $request = FriendRequest::factory()->create([
        'sender_id' => $sender->id,
        'receiver_id' => $user->id,
    ]);

    $user->notify(new FriendRequestReceived($request, $sender));
    $user->notify(new FriendRequestReceived($request, $sender));
    $user->notify(new FriendRequestReceived($request, $sender));

    Livewire::actingAs($user)
        ->test(Bell::class)
        ->call('markAllAsRead')
        ->assertSet('unreadCount', 0);

    expect($user->unreadNotifications()->count())->toBe(0);
});

it('does not let user A mark user B notifications', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $sender = User::factory()->create();
    $request = FriendRequest::factory()->create([
        'sender_id' => $sender->id,
        'receiver_id' => $userB->id,
    ]);

    $userB->notify(new FriendRequestReceived($request, $sender));
    $notification = $userB->notifications()->first();

    Livewire::actingAs($userA)
        ->test(Bell::class)
        ->call('markAsRead', $notification->id);

    expect($notification->fresh()->read_at)->toBeNull();
});
