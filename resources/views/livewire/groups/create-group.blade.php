<form wire:submit="save" class="flex flex-col gap-4" data-test="create-group-form">
    <div>
        <flux:heading size="lg">{{ __('Create a group') }}</flux:heading>
        <flux:text class="mt-1 text-zinc-500">
            {{ __('Give it a name and pick the friends who will share expenses here.') }}
        </flux:text>
    </div>

    <flux:input
        wire:model="name"
        :label="__('Group name')"
        type="text"
        required
        autofocus
        placeholder="e.g. Trip Bali"
        data-test="group-name"
    />

    <flux:textarea
        wire:model="description"
        :label="__('Description (optional)')"
        rows="2"
        placeholder="{{ __('A short note about the group') }}"
    />

    <div>
        <flux:heading size="sm">{{ __('Add friends') }}</flux:heading>
        <flux:text class="text-zinc-500">
            {{ __('Only your friends appear here. You can add more later.') }}
        </flux:text>

        <div class="mt-2 flex max-h-56 flex-col gap-2 overflow-y-auto rounded-lg border border-neutral-200 p-3 dark:border-neutral-700">
            @forelse ($this->friends as $friend)
                <label class="flex cursor-pointer items-center gap-3" wire:key="friend-{{ $friend->id }}">
                    <flux:checkbox
                        wire:model="selectedFriendIds"
                        value="{{ $friend->id }}"
                        data-test="select-friend-{{ $friend->id }}"
                    />
                    <flux:avatar size="sm" :name="$friend->name" :initials="$friend->initials()" />
                    <span class="text-sm">{{ $friend->name }}</span>
                </label>
            @empty
                <flux:text class="text-zinc-500">
                    {{ __('No friends yet. Add friends first to invite them.') }}
                </flux:text>
            @endforelse
        </div>
    </div>

    <div class="flex justify-end gap-2">
        <flux:modal.close>
            <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
        </flux:modal.close>
        <flux:button type="submit" variant="primary" icon="check" data-test="submit-create-group">
            {{ __('Create group') }}
        </flux:button>
    </div>
</form>
