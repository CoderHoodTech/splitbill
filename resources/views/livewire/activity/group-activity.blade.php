<div
    class="rounded-xl border border-neutral-200 bg-white p-5 dark:border-neutral-700 dark:bg-zinc-900"
    data-test="activity-panel"
>
    <flux:heading size="lg" class="mb-3">{{ __('Activity') }}</flux:heading>

    @forelse ($this->activities as $activity)
        @php($p = $activity->payload ?? [])
        <div
            @class([
                'flex items-start gap-3 py-2.5',
                'border-b border-zinc-100 dark:border-zinc-800' => ! $loop->last,
            ])
            data-test="activity-row"
            wire:key="activity-{{ $activity->id }}"
        >
            <flux:avatar
                size="xs"
                :name="$activity->actor?->name ?? __('Unknown')"
                :initials="$activity->actor?->initials() ?? '?'"
            />

            <div class="min-w-0 flex-1">
                <div class="text-sm text-zinc-800 dark:text-zinc-100">
                    @php($actor = $activity->actor?->name ?? __('Unknown'))
                    @switch($activity->verb->value)
                        @case('expense.created')
                            {{ __(':actor added expense ":desc" (:amount).', [
                                'actor' => $actor,
                                'desc' => $p['description'] ?? '',
                                'amount' => \App\Support\Money::format((int) ($p['total_amount'] ?? 0)),
                            ]) }}
                            @break
                        @case('expense.updated')
                            {{ __(':actor updated expense ":desc" (:amount).', [
                                'actor' => $actor,
                                'desc' => $p['description'] ?? '',
                                'amount' => \App\Support\Money::format((int) ($p['total_amount'] ?? 0)),
                            ]) }}
                            @break
                        @case('expense.deleted')
                            {{ __(':actor deleted expense ":desc".', [
                                'actor' => $actor,
                                'desc' => $p['description'] ?? '',
                            ]) }}
                            @break
                        @case('settlement.recorded')
                            {{ __(':from paid :to :amount.', [
                                'from' => $p['from_name'] ?? '',
                                'to' => $p['to_name'] ?? '',
                                'amount' => \App\Support\Money::format((int) ($p['amount'] ?? 0)),
                            ]) }}
                            @break
                        @case('member.added')
                            {{ __(':actor added :member to the group.', [
                                'actor' => $actor,
                                'member' => $p['member_name'] ?? '',
                            ]) }}
                            @break
                        @case('member.removed')
                            {{ __(':actor removed :member from the group.', [
                                'actor' => $actor,
                                'member' => $p['member_name'] ?? '',
                            ]) }}
                            @break
                        @default
                            {{ $activity->verb->value }}
                    @endswitch
                </div>
                <div class="mt-0.5 text-xs text-zinc-500">
                    {{ $activity->created_at?->diffForHumans() }}
                </div>
            </div>
        </div>
    @empty
        <flux:text class="text-zinc-500" data-test="activity-empty">
            {{ __('No activity yet.') }}
        </flux:text>
    @endforelse

    @if ($this->activities->hasPages())
        <div class="mt-4">
            {{ $this->activities->links() }}
        </div>
    @endif
</div>
