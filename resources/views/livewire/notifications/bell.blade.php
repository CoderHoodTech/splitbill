<div class="relative" data-test="notifications-bell" wire:poll.30s>
    <flux:dropdown position="bottom" align="end">
        <button
            type="button"
            class="relative inline-flex h-10 w-10 items-center justify-center rounded-md text-zinc-500 transition hover:bg-zinc-100 dark:text-zinc-400 dark:hover:bg-zinc-800"
            data-test="notifications-bell-toggle"
            aria-label="{{ __('Notifications') }}"
        >
            <flux:icon name="bell" class="size-5" />
            @if ($this->unreadCount > 0)
                <span
                    class="absolute -top-0.5 -right-0.5 inline-flex h-4 min-w-4 items-center justify-center rounded-full bg-rose-500 px-1 text-[10px] font-semibold leading-none text-white"
                    data-test="notifications-bell-badge"
                >
                    {{ $this->unreadCount > 99 ? '99+' : $this->unreadCount }}
                </span>
            @endif
        </button>

        <flux:menu class="w-80! max-w-sm! p-0!">
            <div class="flex items-center justify-between border-b border-zinc-100 px-4 py-3 dark:border-zinc-700">
                <flux:heading size="sm">{{ __('Notifications') }}</flux:heading>
                @if ($this->unreadCount > 0)
                    <flux:button
                        size="xs"
                        variant="ghost"
                        wire:click="markAllAsRead"
                        data-test="notifications-mark-all"
                    >
                        {{ __('Mark all read') }}
                    </flux:button>
                @endif
            </div>

            <div class="max-h-96 overflow-y-auto">
                @forelse ($this->recent as $notification)
                    @php($data = $notification->data)
                    <button
                        type="button"
                        wire:click="markAsRead('{{ $notification->id }}')"
                        @class([
                            'block w-full border-b border-zinc-100 px-4 py-3 text-left text-sm transition last:border-b-0 dark:border-zinc-700',
                            'bg-zinc-50 dark:bg-zinc-800/40' => $notification->read_at === null,
                            'hover:bg-zinc-100 dark:hover:bg-zinc-800/60',
                        ])
                        data-test="notification-item"
                        wire:key="notification-{{ $notification->id }}"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0 flex-1">
                                <div class="text-zinc-900 dark:text-zinc-100">
                                    @switch($data['type'] ?? null)
                                        @case('friend_request.received')
                                            {{ __(':name sent you a friend request.', ['name' => $data['sender_name'] ?? __('Someone')]) }}
                                            @break
                                        @case('friend_request.accepted')
                                            {{ __(':name accepted your friend request.', ['name' => $data['accepter_name'] ?? __('Someone')]) }}
                                            @break
                                        @case('expense.added')
                                            {{ __(':name added expense ":desc" (:amount) in :group.', [
                                                'name' => $data['actor_name'] ?? __('Someone'),
                                                'desc' => $data['description'] ?? '',
                                                'amount' => \App\Support\Money::format((int) ($data['total_amount'] ?? 0)),
                                                'group' => $data['group_name'] ?? '',
                                            ]) }}
                                            @break
                                        @case('expense.updated')
                                            {{ __(':name updated expense ":desc" (:amount) in :group.', [
                                                'name' => $data['actor_name'] ?? __('Someone'),
                                                'desc' => $data['description'] ?? '',
                                                'amount' => \App\Support\Money::format((int) ($data['total_amount'] ?? 0)),
                                                'group' => $data['group_name'] ?? '',
                                            ]) }}
                                            @break
                                        @case('expense.deleted')
                                            {{ __(':name deleted expense ":desc" from :group.', [
                                                'name' => $data['actor_name'] ?? __('Someone'),
                                                'desc' => $data['description'] ?? '',
                                                'group' => $data['group_name'] ?? '',
                                            ]) }}
                                            @break
                                        @case('settlement.received')
                                            {{ __(':name paid you :amount in :group.', [
                                                'name' => $data['payer_name'] ?? __('Someone'),
                                                'amount' => \App\Support\Money::format((int) ($data['amount'] ?? 0)),
                                                'group' => $data['group_name'] ?? '',
                                            ]) }}
                                            @break
                                        @case('group.added')
                                            {{ __(':name added you to :group.', [
                                                'name' => $data['actor_name'] ?? __('Someone'),
                                                'group' => $data['group_name'] ?? '',
                                            ]) }}
                                            @break
                                        @default
                                            {{ $data['type'] ?? __('Notification') }}
                                    @endswitch
                                </div>
                                <div class="mt-1 text-xs text-zinc-500">
                                    {{ $notification->created_at?->diffForHumans() }}
                                </div>
                            </div>
                            @if ($notification->read_at === null)
                                <span class="mt-1 inline-block h-2 w-2 shrink-0 rounded-full bg-indigo-500"></span>
                            @endif
                        </div>
                    </button>
                @empty
                    <div class="px-4 py-8 text-center" data-test="notifications-empty">
                        <flux:text class="text-zinc-500">
                            {{ __('No notifications yet.') }}
                        </flux:text>
                    </div>
                @endforelse
            </div>
        </flux:menu>
    </flux:dropdown>
</div>
