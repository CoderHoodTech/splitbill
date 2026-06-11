<?php

declare(strict_types=1);

namespace App\Actions\Friends;

use App\Enums\FriendRequestStatus;
use App\Exceptions\FriendRequestException;
use App\Models\FriendRequest;
use App\Models\User;
use App\Notifications\FriendRequestReceived;
use Illuminate\Support\Facades\DB;

final class SendFriendRequest
{
    /**
     * Send a friend request from $sender to the user identified by $friendCode.
     *
     * @throws FriendRequestException
     */
    public function execute(User $sender, string $friendCode): FriendRequest
    {
        $code = strtoupper(trim($friendCode));

        $receiver = User::query()->where('friend_code', $code)->first();

        if ($receiver === null) {
            throw FriendRequestException::unknownCode();
        }

        if ($receiver->is($sender)) {
            throw FriendRequestException::cannotAddSelf();
        }

        if ($sender->isFriendsWith($receiver)) {
            throw FriendRequestException::alreadyFriends();
        }

        return DB::transaction(function () use ($sender, $receiver): FriendRequest {
            // Reciprocal pending request: short-circuit by accepting it instead.
            $reciprocal = FriendRequest::query()
                ->where('sender_id', $receiver->getKey())
                ->where('receiver_id', $sender->getKey())
                ->pending()
                ->lockForUpdate()
                ->first();

            if ($reciprocal !== null) {
                app(AcceptFriendRequest::class)->execute($sender, $reciprocal);

                return $reciprocal->refresh();
            }

            $existing = FriendRequest::query()
                ->where('sender_id', $sender->getKey())
                ->where('receiver_id', $receiver->getKey())
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                if ($existing->status === FriendRequestStatus::Pending) {
                    throw FriendRequestException::duplicatePending();
                }

                if ($existing->status === FriendRequestStatus::Declined) {
                    throw FriendRequestException::previouslyDeclined();
                }
            }

            $friendRequest = FriendRequest::query()->create([
                'sender_id' => $sender->getKey(),
                'receiver_id' => $receiver->getKey(),
                'status' => FriendRequestStatus::Pending,
            ]);

            $receiver->notify(new FriendRequestReceived($friendRequest, $sender));

            return $friendRequest;
        });
    }
}
