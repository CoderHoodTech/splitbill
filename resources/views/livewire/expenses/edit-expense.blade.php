<div>
    <flux:modal name="edit-expense" class="md:w-lg">
        @if ($expense)
            <form wire:submit="save" class="flex flex-col gap-4" data-test="edit-expense-form">
                <div>
                    <flux:heading size="lg">{{ __('Edit expense') }}</flux:heading>
                </div>

                <flux:input
                    wire:model="description"
                    :label="__('Description')"
                    required
                    data-test="edit-expense-description"
                />

                <div class="grid gap-3 sm:grid-cols-2">
                    <flux:input
                        wire:model="total_amount"
                        :label="__('Total (Rp)')"
                        type="number"
                        min="1"
                        required
                    />
                    <flux:input
                        wire:model="expense_date"
                        :label="__('Date')"
                        type="date"
                        required
                    />
                </div>

                <flux:select wire:model="payer_id" :label="__('Paid by')">
                    @foreach ($this->members as $member)
                        <flux:select.option value="{{ $member->id }}">{{ $member->name }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:radio.group wire:model.live="split_method" :label="__('Split method')" variant="segmented">
                    <flux:radio value="equal" :label="__('Equal')" />
                    <flux:radio value="exact" :label="__('Exact')" />
                    <flux:radio value="percent" :label="__('Percent')" />
                </flux:radio.group>

                <div>
                    <flux:heading size="sm">{{ __('Participants') }}</flux:heading>
                    <flux:text class="text-xs text-zinc-500">
                        @if ($split_method === 'equal')
                            {{ __('Split equally among the selected members.') }}
                        @elseif ($split_method === 'exact')
                            {{ __('Enter each share in Rupiah. The sum must equal the total.') }}
                        @else
                            {{ __('Enter each percent. The sum must equal 100.') }}
                        @endif
                    </flux:text>

                    <div class="mt-2 flex flex-col gap-2 rounded-lg border border-neutral-200 p-3 dark:border-neutral-700">
                        @foreach ($this->members as $member)
                            <div class="flex items-center gap-3" wire:key="edit-participant-{{ $member->id }}">
                                <label class="flex flex-1 cursor-pointer items-center gap-2">
                                    <flux:checkbox
                                        wire:model.live="participants.{{ $member->id }}"
                                        value="1"
                                    />
                                    <flux:avatar size="xs" :name="$member->name" :initials="$member->initials()" />
                                    <span class="text-sm">{{ $member->name }}</span>
                                </label>

                                @if ($split_method !== 'equal' && ! empty($participants[$member->id]))
                                    <flux:input
                                        wire:model="shareInputs.{{ $member->id }}"
                                        type="number"
                                        step="{{ $split_method === 'percent' ? '0.01' : '1' }}"
                                        min="0"
                                        size="sm"
                                        class="w-28"
                                    />
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>

                @error('total_amount')
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
                        data-test="submit-edit-expense"
                    >
                        <span wire:loading.remove wire:target="save">{{ __('Update expense') }}</span>
                        <span wire:loading wire:target="save">{{ __('Saving…') }}</span>
                    </flux:button>
                </div>
            </form>
        @endif
    </flux:modal>
</div>
