<div>
    <flux:modal :name="'add-expense-' . $group->id" class="md:w-lg">
        <form wire:submit="save" class="flex flex-col gap-4" data-test="add-expense-form">
            <div>
                <flux:heading size="lg">{{ __('Add expense') }}</flux:heading>
                <flux:text class="mt-1 text-zinc-500">
                    {{ __('Log a shared cost. Shares always sum back to the total.') }}
                </flux:text>
            </div>

            <div class="rounded-lg border border-dashed border-neutral-300 p-3 dark:border-neutral-600" data-test="receipt-scan-panel">
                <flux:input
                    type="file"
                    wire:model="receipt"
                    :label="__('Scan a receipt (optional)')"
                    accept="image/*"
                    data-test="receipt-upload"
                />
                <flux:text class="mt-1 text-xs text-zinc-500">
                    {{ __('Photo of the struk. We will prefill the total — you can always edit it.') }}
                </flux:text>

                <div wire:loading wire:target="receipt" class="mt-2 flex items-center gap-2 text-sm text-zinc-600 dark:text-zinc-300">
                    <flux:icon.loading variant="micro" />
                    {{ __('Scanning receipt…') }}
                </div>

                @error('receipt')
                    <flux:text class="mt-2 text-sm text-red-600">{{ $message }}</flux:text>
                @enderror

                @if ($hasOcrResult)
                    <div class="mt-3 rounded-md bg-neutral-50 p-3 text-sm dark:bg-neutral-800" data-test="ocr-result">
                        @if ($ocrError)
                            <flux:callout variant="warning" icon="exclamation-triangle">
                                {{ __('OCR service unavailable. You can still enter the amount manually.') }}
                            </flux:callout>
                        @elseif ($ocrSuggestedTotal !== null)
                            <p data-test="ocr-total-line">
                                {{ __('Detected total') }}:
                                <strong>Rp{{ number_format($ocrSuggestedTotal, 0, ',', '.') }}</strong>
                                @if ($ocrEngine)
                                    <span class="text-xs text-zinc-500">({{ $ocrEngine }})</span>
                                @endif
                            </p>
                        @else
                            <p>{{ __('Could not detect a total. Please enter it manually.') }}</p>
                        @endif

                        @if (! empty($ocrLineItems))
                            <p class="mt-2 text-xs text-zinc-500">{{ __('Line items detected:') }}</p>
                            <ul class="mt-1 max-h-32 overflow-auto text-xs" data-test="ocr-line-items">
                                @foreach ($ocrLineItems as $item)
                                    <li class="flex justify-between gap-2 py-0.5">
                                        <span class="truncate">{{ $item['name'] }}</span>
                                        <span class="tabular-nums text-zinc-500">Rp{{ number_format($item['amount'], 0, ',', '.') }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif

                        <flux:button
                            wire:click="dismissOcr"
                            variant="ghost"
                            size="xs"
                            class="mt-2"
                            data-test="ocr-dismiss"
                        >
                            {{ __('Dismiss') }}
                        </flux:button>
                    </div>
                @endif
            </div>

            <flux:input
                wire:model="description"
                :label="__('Description')"
                placeholder="e.g. Nasi Padang"
                required
                data-test="expense-description"
            />

            <div class="grid gap-3 sm:grid-cols-2">
                <flux:input
                    wire:model="total_amount"
                    :label="__('Total (Rp)')"
                    type="number"
                    min="1"
                    required
                    data-test="expense-total"
                />

                <flux:input
                    wire:model="expense_date"
                    :label="__('Date')"
                    type="date"
                    required
                />
            </div>

            <flux:select
                wire:model="payer_id"
                :label="__('Paid by')"
                data-test="expense-payer"
            >
                @foreach ($this->members as $member)
                    <flux:select.option value="{{ $member->id }}">{{ $member->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:radio.group wire:model.live="split_method" :label="__('Split method')" variant="segmented" data-test="split-method">
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
                        <div class="flex items-center gap-3" wire:key="participant-{{ $member->id }}">
                            <label class="flex flex-1 cursor-pointer items-center gap-2">
                                <flux:checkbox
                                    wire:model.live="participants.{{ $member->id }}"
                                    value="1"
                                    data-test="participant-{{ $member->id }}"
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
                                    :placeholder="$split_method === 'percent' ? '%' : 'Rp'"
                                    data-test="share-input-{{ $member->id }}"
                                />
                            @endif
                        </div>
                    @endforeach
                </div>

                @error('participants')
                    <flux:error name="participants" />
                @enderror
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
                    data-test="submit-add-expense"
                >
                    <span wire:loading.remove wire:target="save">{{ __('Save expense') }}</span>
                    <span wire:loading wire:target="save">{{ __('Saving…') }}</span>
                </flux:button>
            </div>
        </form>
    </flux:modal>
</div>
