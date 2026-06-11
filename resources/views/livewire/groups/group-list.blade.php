<section data-test="group-list" class="flex flex-col gap-4">
    <div class="flex items-center justify-between gap-3">
        <div>
            <flux:heading size="lg">{{ __('My groups') }}</flux:heading>
            <flux:text class="mt-1 text-zinc-500">
                {{ __('Create a group to start splitting bills with friends.') }}
            </flux:text>
        </div>

        <flux:modal.trigger name="create-group">
            <flux:button icon="plus" variant="primary" data-test="open-create-group">
                {{ __('New group') }}
            </flux:button>
        </flux:modal.trigger>
    </div>

    <div class="rounded-xl border border-neutral-200 bg-white p-2 dark:border-neutral-700 dark:bg-zinc-900">
        @forelse ($this->groups as $group)
            <a
                href="{{ route('groups.show', $group) }}"
                wire:navigate
                @class([
                    'flex items-center justify-between gap-3 rounded-lg p-3 transition hover:bg-zinc-50 dark:hover:bg-zinc-800',
                    'border-b border-zinc-100 dark:border-zinc-800' => ! $loop->last,
                ])
                data-test="group-row"
                wire:key="group-{{ $group->id }}"
            >
                <div class="min-w-0">
                    <flux:heading size="sm" class="truncate">{{ $group->name }}</flux:heading>
                    @if ($group->description)
                        <flux:text class="truncate text-zinc-500">{{ $group->description }}</flux:text>
                    @endif
                </div>
                <flux:badge color="zinc" size="sm">
                    {{ __(':n members', ['n' => $group->members_count]) }}
                </flux:badge>
            </a>
        @empty
            <div class="p-4">
                <flux:text class="text-zinc-500">
                    {{ __('No groups yet. Create your first group to get started.') }}
                </flux:text>
            </div>
        @endforelse
    </div>

    <flux:modal name="create-group" class="md:w-md">
        <livewire:groups.create-group />
    </flux:modal>
</section>
