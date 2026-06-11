<x-layouts::app :title="$group->name">
    <div class="flex h-full w-full flex-1 flex-col p-2">
        <flux:button
            :href="route('groups.index')"
            wire:navigate
            variant="ghost"
            size="sm"
            icon="chevron-left"
            class="mb-3 w-fit"
        >
            {{ __('Back to groups') }}
        </flux:button>

        <livewire:groups.show-group :group="$group" />
    </div>
</x-layouts::app>
