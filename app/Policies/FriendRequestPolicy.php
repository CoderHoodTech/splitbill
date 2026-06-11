<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\FriendRequest;
use App\Models\User;

class FriendRequestPolicy
{
    /**
     * Anyone authenticated can list their own incoming/outgoing requests.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Only the sender or the receiver may view a specific request.
     */
    public function view(User $user, FriendRequest $friendRequest): bool
    {
        return $this->isInvolved($user, $friendRequest);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function accept(User $user, FriendRequest $friendRequest): bool
    {
        return (int) $friendRequest->receiver_id === (int) $user->getKey()
            && $friendRequest->isPending();
    }

    public function decline(User $user, FriendRequest $friendRequest): bool
    {
        return (int) $friendRequest->receiver_id === (int) $user->getKey()
            && $friendRequest->isPending();
    }

    private function isInvolved(User $user, FriendRequest $friendRequest): bool
    {
        $key = (int) $user->getKey();

        return (int) $friendRequest->sender_id === $key
            || (int) $friendRequest->receiver_id === $key;
    }
}
