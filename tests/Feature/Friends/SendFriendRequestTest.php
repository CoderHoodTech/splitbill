<?php

declare(strict_types=1);

use App\Actions\Friends\SendFriendRequest;
use App\Enums\FriendRequestStatus;
use App\Exceptions\FriendRequestException;
use App\Models\FriendRequest;
use App\Models\Friendship;
use App\Models\User;

beforeEach(function () {
    $this->action = app(SendFriendRequest::class);
});

it('creates a pending request when a valid friend code is used', function () {
    $sender = User::factory()->create();
    $receiver = User::factory()->create();

    $request = $this->action->execute($sender, $receiver->friend_code);

    expect($request)->toBeInstanceOf(FriendRequest::class)
        ->and($request->sender_id)->toBe($sender->id)
        ->and($request->receiver_id)->toBe($receiver->id)
        ->and($request->status)->toBe(FriendRequestStatus::Pending);
});

it('normalizes the code (uppercase + trim)', function () {
    $sender = User::factory()->create();
    $receiver = User::factory()->create();

    $request = $this->action->execute($sender, '  '.strtolower($receiver->friend_code).'  ');

    expect($request->receiver_id)->toBe($receiver->id);
});

it('rejects an unknown friend code', function () {
    $sender = User::factory()->create();

    expect(fn () => $this->action->execute($sender, 'NOPENOPE'))
        ->toThrow(FriendRequestException::class);
});

it('rejects adding yourself', function () {
    $user = User::factory()->create();

    expect(fn () => $this->action->execute($user, $user->friend_code))
        ->toThrow(FriendRequestException::class);
});

it('rejects when already friends', function () {
    $a = User::factory()->create();
    $b = User::factory()->create();
    Friendship::query()->create(['user_id' => $a->id, 'friend_id' => $b->id]);
    Friendship::query()->create(['user_id' => $b->id, 'friend_id' => $a->id]);

    expect(fn () => $this->action->execute($a, $b->friend_code))
        ->toThrow(FriendRequestException::class);
});

it('rejects a duplicate pending request', function () {
    $sender = User::factory()->create();
    $receiver = User::factory()->create();

    $this->action->execute($sender, $receiver->friend_code);

    expect(fn () => $this->action->execute($sender, $receiver->friend_code))
        ->toThrow(FriendRequestException::class);
});

it('rejects a re-send after the receiver declined', function () {
    $sender = User::factory()->create();
    $receiver = User::factory()->create();

    FriendRequest::factory()->declined()->create([
        'sender_id' => $sender->id,
        'receiver_id' => $receiver->id,
    ]);

    expect(fn () => $this->action->execute($sender, $receiver->friend_code))
        ->toThrow(FriendRequestException::class);
});

it('auto-accepts when the receiver had already sent a pending request back', function () {
    $a = User::factory()->create();
    $b = User::factory()->create();

    // B already asked A.
    $original = FriendRequest::factory()->create([
        'sender_id' => $b->id,
        'receiver_id' => $a->id,
    ]);

    // Now A "sends" to B by code — should short-circuit into accept.
    $this->action->execute($a, $b->friend_code);

    expect($original->fresh()->status)->toBe(FriendRequestStatus::Accepted)
        ->and($a->fresh()->isFriendsWith($b))->toBeTrue()
        ->and($b->fresh()->isFriendsWith($a))->toBeTrue();
});
