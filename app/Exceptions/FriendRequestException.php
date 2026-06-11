<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

class FriendRequestException extends Exception
{
    public static function unknownCode(): self
    {
        return new self(__('Friend code not found.'));
    }

    public static function cannotAddSelf(): self
    {
        return new self(__('You cannot add yourself as a friend.'));
    }

    public static function alreadyFriends(): self
    {
        return new self(__('You are already friends.'));
    }

    public static function duplicatePending(): self
    {
        return new self(__('A friend request is already pending.'));
    }

    public static function previouslyDeclined(): self
    {
        return new self(__('This friend request was already declined.'));
    }

    public static function alreadyResolved(): self
    {
        return new self(__('This friend request has already been responded to.'));
    }
}
