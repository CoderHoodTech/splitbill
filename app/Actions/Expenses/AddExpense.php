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
use App\Models\Group;
use App\Models\User;
use App\Notifications\ExpenseAddedToYourGroup;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

final class AddExpense
{
    public function __construct(
        private readonly CalculateShares $calculateShares,
        private readonly RecordActivity $recordActivity,
    ) {}

    /**
     * Log a shared expense in the group. Payer MAY also be a participant.
     *
     * @param  array<int>  $participantIds  user IDs participating in the expense
     * @param  array<int, int|float>|null  $shareInputs  user_id => share rupiah (exact) or percent
     *
     * @throws ExpenseException
     */
    public function execute(
        Group $group,
        User $payer,
        string $description,
        int $totalAmount,
        SplitMethod $method,
        DateTimeInterface $expenseDate,
        array $participantIds,
        ?array $shareInputs = null,
    ): Expense {
        $description = trim($description);

        if ($description === '') {
            throw new ExpenseException(__('Description is required.'));
        }

        if (! $group->hasMember($payer)) {
            throw ExpenseException::payerNotMember();
        }

        $this->assertAllAreMembers($group, $participantIds);

        $shares = $this->calculateShares->execute(
            method: $method,
            totalAmount: $totalAmount,
            participantIds: $participantIds,
            inputs: $shareInputs,
        );

        return DB::transaction(function () use ($group, $payer, $description, $totalAmount, $method, $expenseDate, $shares): Expense {
            $expense = Expense::query()->create([
                'group_id' => $group->getKey(),
                'payer_id' => $payer->getKey(),
                'description' => $description,
                'total_amount' => $totalAmount,
                'split_method' => $method,
                'expense_date' => $expenseDate,
            ]);

            foreach ($shares as $userId => $shareAmount) {
                ExpenseParticipant::query()->create([
                    'expense_id' => $expense->getKey(),
                    'user_id' => $userId,
                    'share_amount' => $shareAmount,
                ]);
            }

            $this->recordActivity->execute(
                actor: $payer,
                verb: ActivityVerb::ExpenseCreated,
                subject: $expense,
                group: $group,
                payload: [
                    'description' => $expense->description,
                    'total_amount' => (int) $expense->total_amount,
                ],
            );

            $recipients = User::query()
                ->whereIn('id', array_keys($shares))
                ->where('id', '!=', $payer->getKey())
                ->get();

            Notification::send($recipients, new ExpenseAddedToYourGroup($expense, $group, $payer));

            return $expense->fresh(['participants']);
        });
    }

    /**
     * @param  array<int>  $participantIds
     *
     * @throws ExpenseException
     */
    private function assertAllAreMembers(Group $group, array $participantIds): void
    {
        if ($participantIds === []) {
            throw ExpenseException::emptyParticipants();
        }

        $memberIds = $group->groupMembers()->pluck('user_id')->map(fn ($id) => (int) $id)->all();

        foreach ($participantIds as $id) {
            if (! in_array((int) $id, $memberIds, true)) {
                throw ExpenseException::participantNotMember();
            }
        }
    }
}
