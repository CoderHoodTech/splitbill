<section data-test="show-group" class="flex flex-col gap-6">
    {{-- Header --}}
    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
        <div class="min-w-0">
            <flux:heading size="xl" class="truncate">{{ $group->name }}</flux:heading>
            @if ($group->description)
                <flux:text class="mt-1 text-zinc-500">{{ $group->description }}</flux:text>
            @endif
        </div>

        <div class="flex gap-2">
            <flux:modal.trigger :name="'add-expense-' . $group->id">
                <flux:button icon="plus" variant="primary" data-test="open-add-expense">
                    {{ __('Add expense') }}
                </flux:button>
            </flux:modal.trigger>
        </div>
    </div>

    {{-- Members --}}
    <div class="rounded-xl border border-neutral-200 bg-white p-5 dark:border-neutral-700 dark:bg-zinc-900" data-test="members-panel">
        <div class="mb-4 flex items-center justify-between">
            <flux:heading size="lg">{{ __('Members') }}</flux:heading>
            <flux:badge color="zinc" size="sm">{{ $this->members->count() }}</flux:badge>
        </div>

        <div class="flex flex-col gap-2">
            @foreach ($this->members as $member)
                <div
                    class="flex items-center justify-between gap-3 py-1.5"
                    data-test="member-row"
                    wire:key="member-{{ $member->id }}"
                >
                    <div class="flex min-w-0 items-center gap-3">
                        <flux:avatar size="sm" :name="$member->name" :initials="$member->initials()" />
                        <div class="min-w-0">
                            <flux:heading size="sm" class="truncate">{{ $member->name }}</flux:heading>
                            <flux:text class="text-xs text-zinc-500">
                                {{ $group->isOwnedBy($member) ? __('Owner') : __('Member') }}
                            </flux:text>
                        </div>
                    </div>

                    @if ($this->isOwner() && ! $group->isOwnedBy($member))
                        <flux:button
                            size="xs"
                            variant="ghost"
                            icon="x-mark"
                            wire:click="removeMember({{ $member->id }})"
                            wire:confirm="{{ __('Remove :name from the group?', ['name' => $member->name]) }}"
                            data-test="remove-member-{{ $member->id }}"
                        >
                            {{ __('Remove') }}
                        </flux:button>
                    @endif
                </div>
            @endforeach
        </div>

        {{-- Owner: add member from friends --}}
        @if ($this->isOwner() && $this->addableFriends->isNotEmpty())
            <div class="mt-4 flex flex-col gap-2 border-t border-zinc-100 pt-4 dark:border-zinc-800 sm:flex-row sm:items-end">
                <flux:select
                    wire:model="newMemberFriendId"
                    :label="__('Add a friend')"
                    placeholder="{{ __('Choose a friend...') }}"
                    class="flex-1"
                    data-test="new-member-select"
                >
                    @foreach ($this->addableFriends as $friend)
                        <flux:select.option value="{{ $friend->id }}">{{ $friend->name }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:button
                    icon="user-plus"
                    variant="primary"
                    wire:click="addMember"
                    data-test="submit-add-member"
                >
                    {{ __('Add') }}
                </flux:button>
            </div>
        @endif
    </div>

    {{-- Balances --}}
    <div class="rounded-xl border border-neutral-200 bg-white p-5 dark:border-neutral-700 dark:bg-zinc-900" data-test="balances-panel">
        <div class="mb-3 flex items-center justify-between">
            <flux:heading size="lg">{{ __('Balances') }}</flux:heading>
            <flux:modal.trigger :name="'settle-up-' . $group->id">
                <flux:button size="sm" icon="banknotes" variant="primary" data-test="open-settle-up">
                    {{ __('Settle up') }}
                </flux:button>
            </flux:modal.trigger>
        </div>

        <div class="flex flex-col gap-1">
            @foreach ($this->members as $member)
                @php($net = $this->balances[$member->id] ?? 0)
                <div class="flex items-center justify-between py-1" data-test="balance-row-{{ $member->id }}">
                    <div class="flex items-center gap-2">
                        <flux:avatar size="xs" :name="$member->name" :initials="$member->initials()" />
                        <span class="text-sm">{{ $member->name }}</span>
                    </div>
                    <span @class([
                        'font-mono text-sm',
                        'text-emerald-600 dark:text-emerald-400' => $net > 0,
                        'text-rose-600 dark:text-rose-400' => $net < 0,
                        'text-zinc-500' => $net === 0,
                    ])>
                        {{ $net > 0 ? '+' : '' }}{{ \App\Support\Money::format($net) }}
                    </span>
                </div>
            @endforeach
        </div>

        <div class="mt-4 border-t border-zinc-100 pt-4 dark:border-zinc-800">
            <flux:heading size="sm" class="mb-2">{{ __('Who pays whom') }}</flux:heading>

            @if (empty($this->transfers))
                <flux:text class="text-zinc-500" data-test="all-settled">
                    {{ __('All settled up.') }}
                </flux:text>
            @else
                <div class="flex flex-col gap-2">
                    @foreach ($this->transfers as $transfer)
                        @php($fromUser = $this->membersById[$transfer['from']] ?? null)
                        @php($toUser = $this->membersById[$transfer['to']] ?? null)
                        @if ($fromUser && $toUser)
                            <div
                                class="flex items-center justify-between gap-3 rounded-md bg-zinc-50 px-3 py-2 dark:bg-zinc-800/40"
                                data-test="transfer-row"
                                wire:key="transfer-{{ $transfer['from'] }}-{{ $transfer['to'] }}"
                            >
                                <div class="min-w-0 text-sm">
                                    {{ __(':from pays :to', ['from' => $fromUser->name, 'to' => $toUser->name]) }}
                                    <span class="ml-1 font-mono">{{ \App\Support\Money::format($transfer['amount']) }}</span>
                                </div>

                                @if (in_array(auth()->id(), [$transfer['from'], $transfer['to']], true))
                                    <flux:button
                                        size="xs"
                                        variant="ghost"
                                        icon="check"
                                        wire:click="$dispatch('settle-prefill', { from_id: {{ $transfer['from'] }}, to_id: {{ $transfer['to'] }}, amount: {{ $transfer['amount'] }} })"
                                        data-test="settle-{{ $transfer['from'] }}-{{ $transfer['to'] }}"
                                    >
                                        {{ __('Settle') }}
                                    </flux:button>
                                @endif
                            </div>
                        @endif
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    {{-- Expenses --}}
    <div class="rounded-xl border border-neutral-200 bg-white p-5 dark:border-neutral-700 dark:bg-zinc-900" data-test="expenses-panel">
        <flux:heading size="lg" class="mb-3">{{ __('Expenses') }}</flux:heading>

        @forelse ($this->expenses as $expense)
            <div
                @class([
                    'flex flex-col gap-2 py-3 sm:flex-row sm:items-center sm:justify-between',
                    'border-b border-zinc-100 dark:border-zinc-800' => ! $loop->last,
                ])
                data-test="expense-row"
                wire:key="expense-{{ $expense->id }}"
            >
                <div class="min-w-0">
                    <flux:heading size="sm" class="truncate">{{ $expense->description }}</flux:heading>
                    <flux:text class="text-xs text-zinc-500">
                        {{ __('Paid by :name on :date', [
                            'name' => $expense->payer->name,
                            'date' => $expense->expense_date->format('d M Y'),
                        ]) }}
                        ·
                        {{ __(':n participants', ['n' => $expense->participants->count()]) }}
                    </flux:text>
                </div>

                <div class="flex items-center gap-3">
                    <flux:heading size="sm" class="font-mono">
                        {{ \App\Support\Money::format((int) $expense->total_amount) }}
                    </flux:heading>

                    @if ($expense->isEditableBy(auth()->user()))
                        <flux:button
                            size="xs"
                            variant="ghost"
                            icon="pencil-square"
                            wire:click="$dispatch('edit-expense', { expenseId: {{ $expense->id }} })"
                            data-test="edit-expense-{{ $expense->id }}"
                        >
                            {{ __('Edit') }}
                        </flux:button>
                        <flux:button
                            size="xs"
                            variant="ghost"
                            icon="trash"
                            wire:click="deleteExpense({{ $expense->id }})"
                            wire:confirm="{{ __('Delete this expense?') }}"
                            data-test="delete-expense-{{ $expense->id }}"
                        >
                            {{ __('Delete') }}
                        </flux:button>
                    @endif
                </div>
            </div>
        @empty
            <flux:text class="text-zinc-500">
                {{ __('No expenses yet. Click "Add expense" to log the first one.') }}
            </flux:text>
        @endforelse
    </div>

    <livewire:activity.group-activity :group="$group" :key="'group-activity-' . $group->id" />

    <livewire:expenses.add-expense :group="$group" :key="'add-expense-form-' . $group->id" />
    <livewire:expenses.edit-expense :key="'edit-expense-form-' . $group->id" />
    <livewire:groups.settle-up :group="$group" :key="'settle-up-form-' . $group->id" />
</section>
