<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Expense;
use App\Models\User;

class ExpensePolicy
{
    /**
     * Group members may view an expense.
     */
    public function view(User $user, Expense $expense): bool
    {
        return $expense->group->hasMember($user);
    }

    /**
     * Original payer OR group owner may edit/delete.
     */
    public function update(User $user, Expense $expense): bool
    {
        return $expense->isEditableBy($user);
    }

    public function delete(User $user, Expense $expense): bool
    {
        return $expense->isEditableBy($user);
    }
}
