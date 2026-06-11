<?php

declare(strict_types=1);

namespace App\Actions\Friends;

use App\Enums\FriendRequestStatus;
use App\Exceptions\FriendRequestException;
use App\Models\FriendRequest;
use App\Models\User;

final class DeclineFriendRequest
{
    /**
     * Decline a pending friend request. Only the receiver may decline.
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

        $request->forceFill([
            'status' => FriendRequestStatus::Declined,
            'responded_at' => now(),
        ])->save();

        return $request;
    }
}
