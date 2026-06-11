<?php

declare(strict_types=1);

namespace App\Enums;

enum ActivityVerb: string
{
    case ExpenseCreated = 'expense.created';
    case ExpenseUpdated = 'expense.updated';
    case ExpenseDeleted = 'expense.deleted';
    case SettlementRecorded = 'settlement.recorded';
    case MemberAdded = 'member.added';
    case MemberRemoved = 'member.removed';
}
