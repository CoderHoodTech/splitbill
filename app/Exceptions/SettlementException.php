<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

class SettlementException extends Exception
{
    public static function amountMustBePositive(): self
    {
        return new self(__('Settlement amount must be greater than zero.'));
    }

    public static function fromAndToMustDiffer(): self
    {
        return new self(__('Cannot settle to yourself.'));
    }

    public static function fromNotMember(): self
    {
        return new self(__('The payer must be a member of the group.'));
    }

    public static function toNotMember(): self
    {
        return new self(__('The recipient must be a member of the group.'));
    }

    public static function actorNotInvolved(): self
    {
        return new self(__('You can only record settlements you are part of.'));
    }
}
