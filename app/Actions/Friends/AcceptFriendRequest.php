<?php

declare(strict_types=1);

namespace App\Actions\Friends;

use App\Enums\FriendRequestStatus;
use App\Exceptions\FriendRequestException;
use App\Models\FriendRequest;
use App\Models\Friendship;
use App\Models\User;
use App\Notifications\FriendRequestAccepted;
use Illuminate\Support\Facades\DB;

final class AcceptFriendRequest
{
    /**
     * Accept a pending friend request. Only the receiver may accept.
     *
     * @throws FriendRequestException
     */
    public function execute(User $actor, FriendRequest $request): FriendRequest
    {
        if ((int) $request->receiver_id !== (int) $actor->getKey()) {
            throw FriendRequestException::alreadyResolved();
        }

        if (! $request->isPending()) {
            throw FriendRequestException::alreadyResolved();
        }

        return DB::transaction(function () use ($actor, $request): FriendRequest {
            $request->forceFill([
                'status' => FriendRequestStatus::Accepted,
                'responded_at' => now(),
            ])->save();

            Friendship::query()->firstOrCreate([
                'user_id' => $request->sender_id,
                'friend_id' => $request->receiver_id,
            ]);

            Friendship::query()->firstOrCreate([
                'user_id' => $request->receiver_id,
                'friend_id' => $request->sender_id,
            ]);

            $request->sender?->notify(new FriendRequestAccepted($actor));

            return $request;
        });
    }
}
