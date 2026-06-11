<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

class GroupException extends Exception
{
    public static function nameRequired(): self
    {
        return new self(__('A group must have a name.'));
    }

    public static function memberNotFriend(): self
    {
        return new self(__('You can only add friends to a group.'));
    }

    public static function alreadyMember(): self
    {
        return new self(__('This user is already a member of the group.'));
    }

    public static function notOwner(): self
    {
        return new self(__('Only the group owner can perform this action.'));
    }

    public static function cannotRemoveOwner(): self
    {
        return new self(__('The group owner cannot be removed.'));
    }

    public static function memberNotInGroup(): self
    {
        return new self(__('This user is not a member of the group.'));
    }
}
