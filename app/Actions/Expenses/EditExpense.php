<?php

declare(strict_types=1);

namespace App\Actions\Expenses;

use App\Actions\Activities\RecordActivity;
use App\Actions\Splitting\CalculateShares;
use App\Enums\ActivityVerb;
use App\Enums\SplitMethod;
use App\Exceptions\ExpenseException;
use App\Models\Expense;
use App\Models\ExpenseParticipant;
use App\Models\User;
use App\Notifications\ExpenseUpdatedInYourGroup;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

final class EditExpense
{
    public function __construct(
        private readonly CalculateShares $calculateShares,
        private readonly RecordActivity $recordActivity,
    ) {}

    /**
     * Replace an expense's fields and recompute participant shares.
     *
     * Only the original payer OR the group owner may edit.
     *
     * @param  array<int>  $participantIds
     * @param  array<int, int|float>|null  $shareInputs
     *
     * @throws ExpenseException
     */
    public function execute(
        User $actor,
        Expense $expense,
        User $payer,
        string $description,
        int $totalAmount,
        SplitMethod $method,
        DateTimeInterface $expenseDate,
        array $participantIds,
        ?array $shareInputs = null,
    ): Expense {
        if (! $expense->isEditableBy($actor)) {
            throw ExpenseException::notAuthorized();
        }

        $description = trim($description);

        if ($description === '') {
            throw new ExpenseException(__('Description is required.'));
        }

        $group = $expense->group;

        if (! $group->hasMember($payer)) {
            throw ExpenseException::payerNotMember();
        }

        $this->assertAllAreMembers($expense, $participantIds);

        $shares = $this->calculateShares->execute(
            method: $method,
            totalAmount: $totalAmount,
            participantIds: $participantIds,
            inputs: $shareInputs,
        );

        return DB::transaction(function () use ($actor, $expense, $payer, $description, $totalAmount, $method, $expenseDate, $shares): Expense {
            $expense->forceFill([
                'payer_id' => $payer->getKey(),
                'description' => $description,
                'total_amount' => $totalAmount,
                'split_method' => $method,
                'expense_date' => $expenseDate,
            ])->save();

            $expense->participants()->delete();

            foreach ($shares as $userId => $shareAmount) {
                ExpenseParticipant::query()->create([
                    'expense_id' => $expense->getKey(),
                    'user_id' => $userId,
                    'share_amount' => $shareAmount,
                ]);
            }

            $group = $expense->group;

            $this->recordActivity->execute(
                actor: $actor,
                verb: ActivityVerb::ExpenseUpdated,
                subject: $expense,
                group: $group,
                payload: [
                    'description' => $expense->description,
                    'total_amount' => (int) $expense->total_amount,
                ],
            );

            $recipients = User::query()
                ->whereIn('id', array_keys($shares))
                ->where('id', '!=', $actor->getKey())
                ->get();

            Notification::send($recipients, new ExpenseUpdatedInYourGroup($expense, $group, $actor));

            return $expense->fresh(['participants']);
        });
    }

    /**
     * @param  array<int>  $participantIds
     *
     * @throws ExpenseException
     */
    private function assertAllAreMembers(Expense $expense, array $participantIds): void
    {
        if ($participantIds === []) {
            throw ExpenseException::emptyParticipants();
        }

        $memberIds = $expense->group->groupMembers()
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        foreach ($participantIds as $id) {
            if (! in_array((int) $id, $memberIds, true)) {
                throw ExpenseException::participantNotMember();
            }
        }
    }
}
