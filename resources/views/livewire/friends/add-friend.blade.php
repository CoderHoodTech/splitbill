<section
    class="rounded-xl border border-neutral-200 bg-white p-5 dark:border-neutral-700 dark:bg-zinc-900"
    data-test="add-friend"
>
    <flux:heading size="lg">{{ __('Add a friend') }}</flux:heading>
    <flux:text class="mt-1 text-zinc-500">
        {{ __('Enter your friend\'s 8-character code to send them a friend request.') }}
    </flux:text>

    <form wire:submit="send" class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-start">
        <div class="flex-1">
            <flux:input
                wire:model="friend_code"
                :label="__('Friend code')"
                type="text"
                required
                autocomplete="off"
                maxlength="16"
                placeholder="e.g. AB12CD34"
                class="uppercase tracking-widest font-mono"
                data-test="friend-code-input"
            />
        </div>

        <div class="flex sm:pt-7">
            <flux:button
                variant="primary"
                type="submit"
                icon="user-plus"
                class="w-full sm:w-auto"
                wire:loading.attr="disabled"
                wire:target="send"
                data-test="send-friend-request"
            >
                <span wire:loading.remove wire:target="send">{{ __('Send request') }}</span>
                <span wire:loading wire:target="send">{{ __('Sending…') }}</span>
            </flux:button>
        </div>
    </form>
</section>
