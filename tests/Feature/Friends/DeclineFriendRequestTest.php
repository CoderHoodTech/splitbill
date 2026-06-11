<?php

declare(strict_types=1);

use App\Actions\Friends\DeclineFriendRequest;
use App\Enums\FriendRequestStatus;
use App\Exceptions\FriendRequestException;
use App\Models\FriendRequest;
use App\Models\Friendship;
use App\Models\User;

beforeEach(function () {
    $this->action = app(DeclineFriendRequest::class);
});

it('marks the request declined and does not create a friendship', function () {
    $sender = User::factory()->create();
    $receiver = User::factory()->create();
    $request = FriendRequest::factory()->create([
        'sender_id' => $sender->id,
        'receiver_id' => $receiver->id,
    ]);

    $this->action->execute($receiver, $request);

    expect($request->fresh()->status)->toBe(FriendRequestStatus::Declined)
        ->and($request->fresh()->responded_at)->not->toBeNull()
        ->and(Friendship::query()->count())->toBe(0);
});

it('refuses when the actor is not the receiver', function () {
    $sender = User::factory()->create();
    $receiver = User::factory()->create();
    $request = FriendRequest::factory()->create([
        'sender_id' => $sender->id,
        'receiver_id' => $receiver->id,
    ]);

    expect(fn () => $this->action->execute($sender, $request))
        ->toThrow(FriendRequestException::class);
});

it('refuses to decline an already-resolved request', function () {
    $sender = User::factory()->create();
    $receiver = User::factory()->create();
    $request = FriendRequest::factory()->accepted()->create([
        'sender_id' => $sender->id,
        'receiver_id' => $receiver->id,
    ]);

    expect(fn () => $this->action->execute($receiver, $request))
        ->toThrow(FriendRequestException::class);
});
