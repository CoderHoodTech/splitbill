<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

class ExpenseException extends Exception
{
    public static function emptyParticipants(): self
    {
        return new self(__('An expense must have at least one participant.'));
    }

    public static function totalMustBePositive(): self
    {
        return new self(__('Expense total must be greater than zero.'));
    }

    public static function exactSharesMismatch(): self
    {
        return new self(__('Exact shares must sum to the total amount.'));
    }

    public static function percentagesMustSumTo100(): self
    {
        return new self(__('Percentages must sum to 100.'));
    }

    public static function missingShareInput(int $userId): self
    {
        return new self(__('Missing share value for participant :id.', ['id' => $userId]));
    }

    public static function negativeShare(): self
    {
        return new self(__('Shares cannot be negative.'));
    }

    public static function payerNotMember(): self
    {
        return new self(__('The payer must be a member of the group.'));
    }

    public static function participantNotMember(): self
    {
        return new self(__('All participants must be members of the group.'));
    }

    public static function notAuthorized(): self
    {
        return new self(__('You are not allowed to modify this expense.'));
    }

    public static function duplicateParticipant(): self
    {
        return new self(__('A participant may only appear once per expense.'));
    }
}
