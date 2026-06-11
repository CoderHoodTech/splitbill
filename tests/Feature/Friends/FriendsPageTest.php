<?php

declare(strict_types=1);

use App\Livewire\Friends\AddFriend;
use App\Livewire\Friends\FriendList;
use App\Models\FriendRequest;
use App\Models\Friendship;
use App\Models\User;
use Livewire\Livewire;

test('the friends page is gated behind auth', function () {
    $this->get(route('friends.index'))->assertRedirect(route('login'));
});

test('the friends page renders both Livewire components', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('friends.index'))
        ->assertOk()
        ->assertSeeLivewire(AddFriend::class)
        ->assertSeeLivewire(FriendList::class);
});

test('AddFriend creates a pending request and dispatches refresh event', function () {
    $sender = User::factory()->create();
    $receiver = User::factory()->create();

    Livewire::actingAs($sender)
        ->test(AddFriend::class)
        ->set('friend_code', $receiver->friend_code)
        ->call('send')
        ->assertHasNoErrors()
        ->assertSet('friend_code', '')
        ->assertDispatched('friend-request-sent');

    expect(FriendRequest::query()->where('sender_id', $sender->id)
        ->where('receiver_id', $receiver->id)->exists())->toBeTrue();
});

test('AddFriend surfaces a friendly error for an unknown code', function () {
    $sender = User::factory()->create();

    Livewire::actingAs($sender)
        ->test(AddFriend::class)
        ->set('friend_code', 'NOPENOPE')
        ->call('send')
        ->assertHasErrors('friend_code');
});

test('AddFriend rejects self-add with a friendly error', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(AddFriend::class)
        ->set('friend_code', $user->friend_code)
        ->call('send')
        ->assertHasErrors('friend_code');
});

test('FriendList exposes friends, incoming, and sent', function () {
    $me = User::factory()->create();
    $friend = User::factory()->create();
    $incomingSender = User::factory()->create();
    $sentReceiver = User::factory()->create();

    Friendship::query()->create(['user_id' => $me->id, 'friend_id' => $friend->id]);
    Friendship::query()->create(['user_id' => $friend->id, 'friend_id' => $me->id]);

    FriendRequest::factory()->create([
        'sender_id' => $incomingSender->id,
        'receiver_id' => $me->id,
    ]);
    FriendRequest::factory()->create([
        'sender_id' => $me->id,
        'receiver_id' => $sentReceiver->id,
    ]);

    Livewire::actingAs($me)
        ->test(FriendList::class)
        ->assertSee($friend->name)
        ->call('setTab', 'incoming')
        ->assertSee($incomingSender->name)
        ->call('setTab', 'sent')
        ->assertSee($sentReceiver->name);
});

test('FriendList accept flow creates a mutual friendship', function () {
    $me = User::factory()->create();
    $sender = User::factory()->create();
    $request = FriendRequest::factory()->create([
        'sender_id' => $sender->id,
        'receiver_id' => $me->id,
    ]);

    Livewire::actingAs($me)
        ->test(FriendList::class)
        ->call('setTab', 'incoming')
        ->call('accept', $request->id);

    expect($me->fresh()->isFriendsWith($sender))->toBeTrue()
        ->and($sender->fresh()->isFriendsWith($me))->toBeTrue();
});

test('FriendList decline flow leaves no friendship', function () {
    $me = User::factory()->create();
    $sender = User::factory()->create();
    $request = FriendRequest::factory()->create([
        'sender_id' => $sender->id,
        'receiver_id' => $me->id,
    ]);

    Livewire::actingAs($me)
        ->test(FriendList::class)
        ->call('setTab', 'incoming')
        ->call('decline', $request->id);

    expect($me->fresh()->isFriendsWith($sender))->toBeFalse()
        ->and($request->fresh()->status->value)->toBe('declined');
});

test('a user cannot accept a friend request that is not theirs', function () {
    $sender = User::factory()->create();
    $receiver = User::factory()->create();
    $intruder = User::factory()->create();

    $request = FriendRequest::factory()->create([
        'sender_id' => $sender->id,
        'receiver_id' => $receiver->id,
    ]);

    Livewire::actingAs($intruder)
        ->test(FriendList::class)
        ->call('accept', $request->id)
        ->assertStatus(403);
});

test('a user cannot decline a friend request that is not theirs', function () {
    $sender = User::factory()->create();
    $receiver = User::factory()->create();
    $intruder = User::factory()->create();

    $request = FriendRequest::factory()->create([
        'sender_id' => $sender->id,
        'receiver_id' => $receiver->id,
    ]);

    Livewire::actingAs($intruder)
        ->test(FriendList::class)
        ->call('decline', $request->id)
        ->assertStatus(403);
});

test('end-to-end happy path: A adds B by code, B accepts, both are friends', function () {
    $a = User::factory()->create();
    $b = User::factory()->create();

    Livewire::actingAs($a)
        ->test(AddFriend::class)
        ->set('friend_code', $b->friend_code)
        ->call('send')
        ->assertHasNoErrors();

    $request = FriendRequest::query()->where('sender_id', $a->id)
        ->where('receiver_id', $b->id)->firstOrFail();

    Livewire::actingAs($b)
        ->test(FriendList::class)
        ->call('setTab', 'incoming')
        ->call('accept', $request->id);

    expect($a->fresh()->isFriendsWith($b))->toBeTrue()
        ->and($b->fresh()->isFriendsWith($a))->toBeTrue();
});
