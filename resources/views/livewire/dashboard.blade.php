@php($s = $this->summary)

<div class="flex w-full flex-1 flex-col gap-6">
    {{-- Hero: welcome + shareable friend code --}}
    <div
        class="rounded-xl border border-neutral-200 bg-white p-5 dark:border-neutral-700 dark:bg-zinc-900"
        x-data="{
            code: @js(auth()->user()->friend_code),
            copied: false,
            copy() {
                navigator.clipboard.writeText(this.code);
                this.copied = true;
                setTimeout(() => this.copied = false, 1500);
            },
        }"
    >
        <flux:heading size="lg">
            {{ __('Welcome back,') }} {{ auth()->user()->name }} 👋
        </flux:heading>
        <flux:text class="mt-1 text-zinc-500">
            {{ __('Share your friend code so friends can add you.') }}
        </flux:text>
        <div class="mt-2 flex flex-wrap items-center gap-2">
            <div
                class="rounded-lg bg-zinc-100 px-3 py-1.5 font-mono text-sm tracking-widest dark:bg-zinc-800"
                data-test="dashboard-friend-code"
            >{{ auth()->user()->friend_code }}</div>
            <flux:button size="sm" type="button" variant="ghost" icon="clipboard" x-on:click="copy()">
                <span x-text="copied ? @js(__('Copied!')) : @js(__('Copy'))"></span>
            </flux:button>
        </div>
    </div>

    {{-- Balance summary: the heart of the dashboard --}}
    <div class="grid auto-rows-min gap-4 sm:grid-cols-3" data-test="balance-summary">
        {{-- You're owed --}}
        <div class="rounded-xl border border-neutral-200 bg-white p-5 dark:border-neutral-700 dark:bg-zinc-900">
            <div class="flex items-center gap-2 text-emerald-600 dark:text-emerald-400">
                <flux:icon name="arrow-trending-up" variant="micro" />
                <flux:text class="text-xs font-medium uppercase tracking-wide">{{ __("You're owed") }}</flux:text>
            </div>
            <p class="mt-2 text-2xl font-semibold text-emerald-600 dark:text-emerald-400" data-test="total-owed-to-you">
                {{ \App\Support\Money::format($s['total_owed_to_you']) }}
            </p>
        </div>

        {{-- You owe --}}
        <div class="rounded-xl border border-neutral-200 bg-white p-5 dark:border-neutral-700 dark:bg-zinc-900">
            <div class="flex items-center gap-2 text-rose-600 dark:text-rose-400">
                <flux:icon name="arrow-trending-down" variant="micro" />
                <flux:text class="text-xs font-medium uppercase tracking-wide">{{ __('You owe') }}</flux:text>
            </div>
            <p class="mt-2 text-2xl font-semibold text-rose-600 dark:text-rose-400" data-test="total-you-owe">
                {{ \App\Support\Money::format($s['total_you_owe']) }}
            </p>
        </div>

        {{-- Net --}}
        <div class="rounded-xl border border-neutral-200 bg-white p-5 dark:border-neutral-700 dark:bg-zinc-900">
            <div class="flex items-center gap-2 text-zinc-500">
                <flux:icon name="scale" variant="micro" />
                <flux:text class="text-xs font-medium uppercase tracking-wide">{{ __('Net balance') }}</flux:text>
            </div>
            <p @class([
                'mt-2 text-2xl font-semibold',
                'text-emerald-600 dark:text-emerald-400' => $s['net'] > 0,
                'text-rose-600 dark:text-rose-400' => $s['net'] < 0,
                'text-zinc-700 dark:text-zinc-200' => $s['net'] === 0,
            ]) data-test="net-balance">
                {{ \App\Support\Money::format($s['net']) }}
            </p>
            <flux:text class="mt-1 text-xs text-zinc-400">
                @if ($s['net'] > 0)
                    {{ __('Overall, others owe you.') }}
                @elseif ($s['net'] < 0)
                    {{ __('Overall, you owe others.') }}
                @else
                    {{ __("You're all settled up.") }}
                @endif
            </flux:text>
        </div>
    </div>

    {{-- Quick counts --}}
    <div class="grid grid-cols-3 gap-4">
        <a href="{{ route('groups.index') }}" wire:navigate
           class="rounded-xl border border-neutral-200 bg-white p-4 transition hover:border-neutral-300 hover:shadow-sm dark:border-neutral-700 dark:bg-zinc-900 dark:hover:border-neutral-600">
            <div class="flex items-center gap-2 text-zinc-500">
                <flux:icon name="user-group" variant="micro" />
                <flux:text class="text-xs">{{ __('Groups') }}</flux:text>
            </div>
            <p class="mt-1 text-xl font-semibold" data-test="groups-count">{{ $s['groups_count'] }}</p>
        </a>

        <a href="{{ route('friends.index') }}" wire:navigate
           class="rounded-xl border border-neutral-200 bg-white p-4 transition hover:border-neutral-300 hover:shadow-sm dark:border-neutral-700 dark:bg-zinc-900 dark:hover:border-neutral-600">
            <div class="flex items-center gap-2 text-zinc-500">
                <flux:icon name="users" variant="micro" />
                <flux:text class="text-xs">{{ __('Friends') }}</flux:text>
            </div>
            <p class="mt-1 text-xl font-semibold" data-test="friends-count">{{ $s['friends_count'] }}</p>
        </a>

        <a href="{{ route('friends.index') }}" wire:navigate
           class="rounded-xl border border-neutral-200 bg-white p-4 transition hover:border-neutral-300 hover:shadow-sm dark:border-neutral-700 dark:bg-zinc-900 dark:hover:border-neutral-600">
            <div class="flex items-center gap-2 text-zinc-500">
                <flux:icon name="bell" variant="micro" />
                <flux:text class="text-xs">{{ __('Pending requests') }}</flux:text>
            </div>
            <p class="mt-1 flex items-center gap-2 text-xl font-semibold" data-test="pending-requests-count">
                {{ $s['pending_requests_count'] }}
                @if ($s['pending_requests_count'] > 0)
                    <flux:badge size="sm" color="amber">{{ __('new') }}</flux:badge>
                @endif
            </p>
        </a>
    </div>

    {{-- Your groups, each with your position in it --}}
    <div class="rounded-xl border border-neutral-200 bg-white dark:border-neutral-700 dark:bg-zinc-900">
        <div class="flex items-center justify-between border-b border-neutral-100 px-5 py-3 dark:border-neutral-800">
            <flux:heading size="sm">{{ __('Your groups') }}</flux:heading>
            <flux:button href="{{ route('groups.index') }}" wire:navigate size="xs" variant="ghost" icon="plus">
                {{ __('New group') }}
            </flux:button>
        </div>

        @forelse ($s['groups'] as $group)
            <a
                href="{{ route('groups.show', $group['id']) }}"
                wire:navigate
                class="flex items-center justify-between gap-3 border-b border-neutral-50 px-5 py-3 transition last:border-0 hover:bg-neutral-50 dark:border-neutral-800/60 dark:hover:bg-neutral-800/40"
                wire:key="dash-group-{{ $group['id'] }}"
                data-test="dashboard-group-{{ $group['id'] }}"
            >
                <div class="flex min-w-0 items-center gap-3">
                    <flux:avatar size="sm" :name="$group['name']" />
                    <div class="min-w-0">
                        <p class="truncate font-medium">{{ $group['name'] }}</p>
                        <p class="text-xs text-zinc-500">
                            {{ trans_choice('{1} :count member|[2,*] :count members', $group['members_count'], ['count' => $group['members_count']]) }}
                        </p>
                    </div>
                </div>
                <div class="shrink-0 text-right">
                    @if ($group['your_balance'] > 0)
                        <p class="font-semibold text-emerald-600 dark:text-emerald-400">+{{ \App\Support\Money::format($group['your_balance']) }}</p>
                        <p class="text-xs text-zinc-400">{{ __("you're owed") }}</p>
                    @elseif ($group['your_balance'] < 0)
                        <p class="font-semibold text-rose-600 dark:text-rose-400">{{ \App\Support\Money::format($group['your_balance']) }}</p>
                        <p class="text-xs text-zinc-400">{{ __('you owe') }}</p>
                    @else
                        <p class="font-medium text-zinc-500">{{ __('settled') }}</p>
                    @endif
                </div>
            </a>
        @empty
            <div class="flex flex-col items-center gap-3 px-5 py-10 text-center">
                <flux:icon name="user-group" class="text-zinc-300 dark:text-zinc-600" />
                <flux:text class="text-zinc-500">{{ __("You're not in any group yet.") }}</flux:text>
                <flux:button href="{{ route('groups.index') }}" wire:navigate size="sm" variant="primary" icon="plus">
                    {{ __('Create your first group') }}
                </flux:button>
            </div>
        @endforelse
    </div>
</div>
