<div>
    <flux:modal :name="'settle-up-' . $group->id" class="md:w-md">
        <form wire:submit="save" class="flex flex-col gap-4" data-test="settle-up-form">
            <div>
                <flux:heading size="lg">{{ __('Record settlement') }}</flux:heading>
                <flux:text class="mt-1 text-zinc-500">
                    {{ __('Log a payment made between two members. Balances update once saved.') }}
                </flux:text>
            </div>

            <flux:select
                wire:model="from_id"
                :label="__('From (paid)')"
                data-test="settle-from"
            >
                @foreach ($this->members as $member)
                    <flux:select.option value="{{ $member->id }}">{{ $member->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select
                wire:model="to_id"
                :label="__('To (received)')"
                placeholder="{{ __('Choose a recipient...') }}"
                data-test="settle-to"
            >
                @foreach ($this->members as $member)
                    <flux:select.option value="{{ $member->id }}">{{ $member->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:input
                wire:model="amount"
                :label="__('Amount (Rp)')"
                type="number"
                min="1"
                required
                data-test="settle-amount"
            />

            @error('amount')
                <flux:callout variant="danger" icon="exclamation-triangle">{{ $message }}</flux:callout>
            @enderror

            @error('to_id')
                <flux:callout variant="danger" icon="exclamation-triangle">{{ $message }}</flux:callout>
            @enderror

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button
                    type="submit"
                    variant="primary"
                    icon="check"
                    wire:loading.attr="disabled"
                    wire:target="save"
                    data-test="submit-settle"
                >
                    <span wire:loading.remove wire:target="save">{{ __('Record settlement') }}</span>
                    <span wire:loading wire:target="save">{{ __('Saving…') }}</span>
                </flux:button>
            </div>
        </form>
    </flux:modal>
</div>
