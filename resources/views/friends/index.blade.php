<x-layouts::app :title="__('Friends')">
    <div class="flex h-full w-full flex-1 flex-col gap-6 p-2">
        <div>
            <flux:heading size="xl">{{ __('Friends') }}</flux:heading>
            <flux:text class="text-zinc-500">
                {{ __('Add friends by their unique code to split bills together.') }}
            </flux:text>
        </div>

        <livewire:friends.add-friend />

        <livewire:friends.friend-list />
    </div>
</x-layouts::app>
