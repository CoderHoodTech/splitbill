<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading class="sr-only">{{ __('Profile settings') }}</flux:heading>

    <x-settings.layout :heading="__('Profile')" :subheading="__('Update your name and email address')">
        <form wire:submit="updateProfileInformation" class="my-6 w-full space-y-6">
            <flux:input wire:model="name" :label="__('Name')" type="text" required autofocus autocomplete="name" />

            <div>
                <flux:input wire:model="email" :label="__('Email')" type="email" required autocomplete="email" />

            </div>

            <div class="flex items-center gap-4">
                <flux:button variant="primary" type="submit">{{ __('Save') }}</flux:button>
            </div>
        </form>

        <div class="my-6 w-full space-y-2" data-test="friend-code-section">
            <flux:heading size="sm">{{ __('Friend code') }}</flux:heading>
            <flux:text class="text-zinc-500">
                {{ __('Share this code so friends can add you on SplitBill.') }}
            </flux:text>

            <div
                x-data="{
                    code: @js(auth()->user()->friend_code),
                    copied: false,
                    copy() {
                        navigator.clipboard.writeText(this.code);
                        this.copied = true;
                        setTimeout(() => this.copied = false, 1500);
                    },
                }"
                class="mt-2 flex w-full max-w-sm items-center gap-2"
            >
                <div
                    class="flex-1 rounded-lg border border-zinc-200 bg-zinc-50 px-3 py-2 font-mono text-base tracking-widest dark:border-zinc-700 dark:bg-zinc-800"
                    data-test="friend-code-value"
                >{{ auth()->user()->friend_code }}</div>

                <flux:button type="button" variant="primary" icon="clipboard" x-on:click="copy()">
                    <span x-text="copied ? @js(__('Copied!')) : @js(__('Copy'))"></span>
                </flux:button>
            </div>
        </div>

            <livewire:settings.delete-user-form />
    </x-settings.layout>
</section>
