<?php

declare(strict_types=1);

namespace App\Actions\Expenses;

use App\Actions\Activities\RecordActivity;
use App\Enums\ActivityVerb;
use App\Exceptions\ExpenseException;
use App\Models\Expense;
use App\Models\User;
use App\Notifications\ExpenseDeletedInYourGroup;
use Illuminate\Support\Facades\Notification;

final class DeleteExpense
{
    public function __construct(private readonly RecordActivity $recordActivity) {}

    /**
     * Hard-delete an expense. Only the original payer OR the group owner may delete.
     *
     * Participants cascade-delete via the foreign key. MVP scope; soft-delete is BL-014.
     *
     * @throws ExpenseException
     */
    public function execute(User $actor, Expense $expense): void
    {
        if (! $expense->isEditableBy($actor)) {
            throw ExpenseException::notAuthorized();
        }

        $group = $expense->group;
        $snapshot = [
            'expense_id' => $expense->getKey(),
            'description' => $expense->description,
            'total_amount' => (int) $expense->total_amount,
        ];
        $participantIds = $expense->participants()->pluck('user_id')->all();

        $expense->delete();

        $this->recordActivity->execute(
            actor: $actor,
            verb: ActivityVerb::ExpenseDeleted,
            subject: null,
            group: $group,
            payload: $snapshot,
        );

        $recipients = User::query()
            ->whereIn('id', $participantIds)
            ->where('id', '!=', $actor->getKey())
            ->get();

        Notification::send($recipients, new ExpenseDeletedInYourGroup($group, $actor, $snapshot));
    }
}
