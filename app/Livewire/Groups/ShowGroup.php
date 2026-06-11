<?php

declare(strict_types=1);

namespace App\Livewire\Groups;

use App\Actions\Expenses\DeleteExpense;
use App\Actions\Groups\AddGroupMember;
use App\Actions\Groups\RemoveGroupMember;
use App\Actions\Settlements\CalculateGroupBalances;
use App\Actions\Settlements\SimplifyDebts;
use App\Exceptions\ExpenseException;
use App\Exceptions\GroupException;
use App\Models\Expense;
use App\Models\Group;
use App\Models\User;
use Flux\Flux;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class ShowGroup extends Component
{
    public Group $group;

    public string $newMemberFriendId = '';

    public function mount(Group $group): void
    {
        if (! Auth::user()->can('view', $group)) {
            throw new AuthorizationException;
        }

        $this->group = $group;
    }

    /**
     * @return Collection<int, User>
     */
    #[Computed]
    public function members(): Collection
    {
        return $this->group->members()
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, Expense>
     */
    #[Computed]
    public function expenses(): Collection
    {
        return $this->group->expenses()
            ->with(['payer', 'group', 'participants.user'])
            ->orderByDesc('expense_date')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Net balance per member, in rupiah. Positive = owed; negative = owes.
     *
     * @return array<int, int>
     */
    #[Computed]
    public function balances(): array
    {
        return app(CalculateGroupBalances::class)->execute($this->group);
    }

    /**
     * Minimal "who pays whom" transfers derived from {@see $balances}.
     *
     * @return list<array{from:int,to:int,amount:int}>
     */
    #[Computed]
    public function transfers(): array
    {
        return app(SimplifyDebts::class)->execute($this->balances);
    }

    /**
     * @return Collection<int, User>
     */
    #[Computed]
    public function membersById(): Collection
    {
        return $this->members->keyBy('id');
    }

    /**
     * Friends of the current user who are not yet members of this group.
     *
     * @return Collection<int, User>
     */
    #[Computed]
    public function addableFriends(): Collection
    {
        if (! $this->group->isOwnedBy(Auth::user())) {
            return new Collection;
        }

        $memberIds = $this->group->groupMembers()->pluck('user_id')->all();

        return Auth::user()
            ->friends()
            ->whereNotIn('users.id', $memberIds)
            ->orderBy('name')
            ->get();
    }

    public function isOwner(): bool
    {
        return $this->group->isOwnedBy(Auth::user());
    }

    public function addMember(AddGroupMember $action): void
    {
        if (! Auth::user()->can('manageMembers', $this->group)) {
            throw new AuthorizationException;
        }

        if ($this->newMemberFriendId === '') {
            $this->addError('newMemberFriendId', __('Pick a friend to add.'));

            return;
        }

        $friend = User::query()->find((int) $this->newMemberFriendId);

        if ($friend === null) {
            $this->addError('newMemberFriendId', __('Unknown user.'));

            return;
        }

        try {
            $action->execute(Auth::user(), $this->group, $friend);
        } catch (GroupException $e) {
            $this->addError('newMemberFriendId', $e->getMessage());

            return;
        }

        $this->reset('newMemberFriendId');
        unset($this->members, $this->membersById, $this->addableFriends, $this->balances, $this->transfers);

        Flux::toast(variant: 'success', text: __(':name added to the group.', ['name' => $friend->name]));
    }

    public function removeMember(int $userId, RemoveGroupMember $action): void
    {
        if (! Auth::user()->can('manageMembers', $this->group)) {
            throw new AuthorizationException;
        }

        $member = User::query()->find($userId);

        if ($member === null) {
            return;
        }

        try {
            $action->execute(Auth::user(), $this->group, $member);
        } catch (GroupException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());

            return;
        }

        unset($this->members, $this->membersById, $this->addableFriends, $this->balances, $this->transfers);

        Flux::toast(variant: 'success', text: __(':name removed from the group.', ['name' => $member->name]));
    }

    public function deleteExpense(int $expenseId, DeleteExpense $action): void
    {
        $expense = Expense::query()->where('group_id', $this->group->getKey())->find($expenseId);

        if ($expense === null) {
            return;
        }

        if (! Auth::user()->can('delete', $expense)) {
            throw new AuthorizationException;
        }

        try {
            $action->execute(Auth::user(), $expense);
        } catch (ExpenseException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());

            return;
        }

        unset($this->expenses, $this->balances, $this->transfers);

        Flux::toast(variant: 'success', text: __('Expense deleted.'));
    }

    #[On('expense-saved')]
    public function refreshExpenses(): void
    {
        unset($this->expenses, $this->balances, $this->transfers);
    }

    #[On('settlement-recorded')]
    public function refreshSettlements(): void
    {
        unset($this->balances, $this->transfers);
    }
}
