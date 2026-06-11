<?php

declare(strict_types=1);

namespace App\Livewire\Expenses;

use App\Actions\Expenses\EditExpense as EditExpenseAction;
use App\Enums\SplitMethod;
use App\Exceptions\ExpenseException;
use App\Models\Expense;
use App\Models\User;
use Flux\Flux;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Validate;
use Livewire\Component;

class EditExpense extends Component
{
    public ?Expense $expense = null;

    #[Validate('required|string|min:2|max:255')]
    public string $description = '';

    #[Validate('required|integer|min:1|max:9999999999')]
    public int $total_amount = 0;

    #[Validate('required|integer|exists:users,id')]
    public int $payer_id = 0;

    #[Validate('required|date')]
    public string $expense_date = '';

    #[Validate('required|in:equal,exact,percent')]
    public string $split_method = 'equal';

    /**
     * @var array<int, int>
     */
    public array $participants = [];

    /**
     * @var array<int, string>
     */
    public array $shareInputs = [];

    #[On('edit-expense')]
    public function open(int $expenseId): void
    {
        $expense = Expense::query()->with(['group', 'participants'])->find($expenseId);

        if ($expense === null) {
            return;
        }

        if (! Auth::user()->can('update', $expense)) {
            throw new AuthorizationException;
        }

        $this->expense = $expense;
        $this->description = $expense->description;
        $this->total_amount = (int) $expense->total_amount;
        $this->payer_id = (int) $expense->payer_id;
        $this->expense_date = $expense->expense_date->toDateString();
        $this->split_method = $expense->split_method->value;

        $this->participants = [];
        $this->shareInputs = [];

        $existing = $expense->participants->keyBy('user_id');

        foreach ($expense->group->groupMembers()->pluck('user_id') as $userId) {
            $userId = (int) $userId;
            $isParticipant = $existing->has($userId);
            $this->participants[$userId] = $isParticipant ? 1 : 0;

            if ($isParticipant) {
                $share = (int) $existing->get($userId)->share_amount;
                $this->shareInputs[$userId] = $expense->split_method === SplitMethod::Exact
                    ? (string) $share
                    : '';
            } else {
                $this->shareInputs[$userId] = '';
            }
        }

        Flux::modal('edit-expense')->show();
    }

    /**
     * @return Collection<int, User>
     */
    #[Computed]
    public function members(): Collection
    {
        return $this->expense?->group->members()->orderBy('name')->get() ?? new Collection;
    }

    public function save(EditExpenseAction $action): void
    {
        if ($this->expense === null) {
            return;
        }

        $this->validate();

        $selected = $this->selectedParticipantIds();

        if ($selected === []) {
            $this->addError('participants', __('Pick at least one participant.'));

            return;
        }

        $inputs = null;
        $method = SplitMethod::from($this->split_method);

        if ($method !== SplitMethod::Equal) {
            $inputs = [];
            foreach ($selected as $id) {
                $raw = $this->shareInputs[$id] ?? '';
                $inputs[$id] = $method === SplitMethod::Exact ? (int) $raw : (float) $raw;
            }
        }

        try {
            $action->execute(
                actor: Auth::user(),
                expense: $this->expense,
                payer: User::query()->findOrFail($this->payer_id),
                description: $this->description,
                totalAmount: $this->total_amount,
                method: $method,
                expenseDate: now()->parse($this->expense_date),
                participantIds: $selected,
                shareInputs: $inputs,
            );
        } catch (ExpenseException $e) {
            $this->addError('total_amount', $e->getMessage());

            return;
        }

        $this->dispatch('expense-saved');
        Flux::modal('edit-expense')->close();
        Flux::toast(variant: 'success', text: __('Expense updated.'));

        $this->expense = null;
    }

    /**
     * @return array<int>
     */
    private function selectedParticipantIds(): array
    {
        $ids = [];
        foreach ($this->participants as $userId => $checked) {
            if ($checked) {
                $ids[] = (int) $userId;
            }
        }

        return $ids;
    }
}
