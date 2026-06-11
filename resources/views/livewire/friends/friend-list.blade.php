<section data-test="friend-list" class="flex flex-col gap-4">
    {{-- Tabs --}}
    <div class="flex w-full overflow-x-auto rounded-xl border border-neutral-200 bg-white p-1 dark:border-neutral-700 dark:bg-zinc-900">
        @php
            $tabs = [
                'friends' => __('Friends') . ' (' . $this->friends->count() . ')',
                'incoming' => __('Incoming') . ' (' . $this->incoming->count() . ')',
                'sent' => __('Sent') . ' (' . $this->sent->count() . ')',
            ];
        @endphp

        @foreach ($tabs as $key => $label)
            <button
                type="button"
                wire:click="setTab('{{ $key }}')"
                @class([
                    'flex-1 rounded-lg px-3 py-2 text-sm font-medium transition',
                    'bg-zinc-900 text-white dark:bg-zinc-100 dark:text-zinc-900' => $tab === $key,
                    'text-zinc-600 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800' => $tab !== $key,
                ])
                data-test="tab-{{ $key }}"
            >
                {{ $label }}
            </button>
        @endforeach
    </div>

    {{-- Friends list --}}
    @if ($tab === 'friends')
        <div class="rounded-xl border border-neutral-200 bg-white p-5 dark:border-neutral-700 dark:bg-zinc-900" data-test="panel-friends">
            @forelse ($this->friends as $friend)
                <div
                    @class([
                        'flex items-center justify-between gap-3 py-3',
                        'border-b border-zinc-100 dark:border-zinc-800' => ! $loop->last,
                    ])
                    data-test="friend-row"
                    wire:key="friend-{{ $friend->id }}"
                >
                    <div class="flex min-w-0 items-center gap-3">
                        <flux:avatar :name="$friend->name" :initials="$friend->initials()" />
                        <div class="min-w-0">
                            <flux:heading size="sm" class="truncate">{{ $friend->name }}</flux:heading>
                            <flux:text class="truncate text-zinc-500 font-mono text-xs">{{ $friend->friend_code }}</flux:text>
                        </div>
                    </div>
                </div>
            @empty
                <flux:text class="text-zinc-500">
                    {{ __('No friends yet. Share your friend code to get started.') }}
                </flux:text>
            @endforelse
        </div>
    @endif

    {{-- Incoming requests --}}
    @if ($tab === 'incoming')
        <div class="rounded-xl border border-neutral-200 bg-white p-5 dark:border-neutral-700 dark:bg-zinc-900" data-test="panel-incoming">
            @forelse ($this->incoming as $request)
                <div
                    @class([
                        'flex flex-col gap-3 py-3 sm:flex-row sm:items-center sm:justify-between',
                        'border-b border-zinc-100 dark:border-zinc-800' => ! $loop->last,
                    ])
                    data-test="incoming-row"
                    wire:key="incoming-{{ $request->id }}"
                >
                    <div class="flex min-w-0 items-center gap-3">
                        <flux:avatar :name="$request->sender->name" :initials="$request->sender->initials()" />
                        <div class="min-w-0">
                            <flux:heading size="sm" class="truncate">{{ $request->sender->name }}</flux:heading>
                            <flux:text class="truncate text-zinc-500 font-mono text-xs">{{ $request->sender->friend_code }}</flux:text>
                        </div>
                    </div>

                    <div class="flex gap-2">
                        <flux:button
                            size="sm"
                            variant="primary"
                            wire:click="accept({{ $request->id }})"
                            data-test="accept-request"
                        >
                            {{ __('Accept') }}
                        </flux:button>
                        <flux:button
                            size="sm"
                            variant="ghost"
                            wire:click="decline({{ $request->id }})"
                            data-test="decline-request"
                        >
                            {{ __('Decline') }}
                        </flux:button>
                    </div>
                </div>
            @empty
                <flux:text class="text-zinc-500">
                    {{ __('No incoming friend requests.') }}
                </flux:text>
            @endforelse
        </div>
    @endif

    {{-- Sent requests --}}
    @if ($tab === 'sent')
        <div class="rounded-xl border border-neutral-200 bg-white p-5 dark:border-neutral-700 dark:bg-zinc-900" data-test="panel-sent">
            @forelse ($this->sent as $request)
                <div
                    @class([
                        'flex items-center justify-between gap-3 py-3',
                        'border-b border-zinc-100 dark:border-zinc-800' => ! $loop->last,
                    ])
                    data-test="sent-row"
                    wire:key="sent-{{ $request->id }}"
                >
                    <div class="flex min-w-0 items-center gap-3">
                        <flux:avatar :name="$request->receiver->name" :initials="$request->receiver->initials()" />
                        <div class="min-w-0">
                            <flux:heading size="sm" class="truncate">{{ $request->receiver->name }}</flux:heading>
                            <flux:text class="truncate text-zinc-500 font-mono text-xs">{{ $request->receiver->friend_code }}</flux:text>
                        </div>
                    </div>
                    <flux:badge color="zinc" size="sm">{{ __('Pending') }}</flux:badge>
                </div>
            @empty
                <flux:text class="text-zinc-500">
                    {{ __('You have not sent any friend requests.') }}
                </flux:text>
            @endforelse
        </div>
    @endif
</section>
