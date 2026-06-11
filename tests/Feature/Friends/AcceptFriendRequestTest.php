<?php

declare(strict_types=1);

use App\Actions\Friends\AcceptFriendRequest;
use App\Enums\FriendRequestStatus;
use App\Exceptions\FriendRequestException;
use App\Models\FriendRequest;
use App\Models\Friendship;
use App\Models\User;

beforeEach(function () {
    $this->action = app(AcceptFriendRequest::class);
});

it('creates a mutual friendship and marks the request accepted', function () {
    $sender = User::factory()->create();
    $receiver = User::factory()->create();
    $request = FriendRequest::factory()->create([
        'sender_id' => $sender->id,
        'receiver_id' => $receiver->id,
    ]);

    $this->action->execute($receiver, $request);

    expect($request->fresh()->status)->toBe(FriendRequestStatus::Accepted)
        ->and($request->fresh()->responded_at)->not->toBeNull()
        ->and(Friendship::query()->where('user_id', $sender->id)->where('friend_id', $receiver->id)->exists())->toBeTrue()
        ->and(Friendship::query()->where('user_id', $receiver->id)->where('friend_id', $sender->id)->exists())->toBeTrue();
});

it('refuses when the actor is not the receiver', function () {
    $sender = User::factory()->create();
    $receiver = User::factory()->create();
    $intruder = User::factory()->create();
    $request = FriendRequest::factory()->create([
        'sender_id' => $sender->id,
        'receiver_id' => $receiver->id,
    ]);

    expect(fn () => $this->action->execute($intruder, $request))
        ->toThrow(FriendRequestException::class);
});

it('refuses to accept a request that is no longer pending', function () {
    $sender = User::factory()->create();
    $receiver = User::factory()->create();
    $request = FriendRequest::factory()->declined()->create([
        'sender_id' => $sender->id,
        'receiver_id' => $receiver->id,
    ]);

    expect(fn () => $this->action->execute($receiver, $request))
        ->toThrow(FriendRequestException::class);
});
