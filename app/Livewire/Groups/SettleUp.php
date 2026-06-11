<?php

declare(strict_types=1);

namespace App\Livewire\Groups;

use App\Actions\Settlements\RecordSettlement;
use App\Exceptions\SettlementException;
use App\Models\Group;
use App\Models\User;
use Flux\Flux;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Validate;
use Livewire\Component;

class SettleUp extends Component
{
    public Group $group;

    #[Validate('required|integer|exists:users,id')]
    public int $from_id = 0;

    #[Validate('required|integer|exists:users,id|different:from_id')]
    public int $to_id = 0;

    #[Validate('required|integer|min:1|max:9999999999')]
    public int $amount = 0;

    public function mount(Group $group): void
    {
        if (! Auth::user()->can('recordSettlement', $group)) {
            throw new AuthorizationException;
        }

        $this->group = $group;
        $this->from_id = (int) Auth::id();
    }

    /**
     * @return Collection<int, User>
     */
    #[Computed]
    public function members(): Collection
    {
        return $this->group->members()->orderBy('name')->get();
    }

    #[On('settle-prefill')]
    public function prefill(int $from_id, int $to_id, int $amount = 0): void
    {
        $this->from_id = $from_id;
        $this->to_id = $to_id;
        $this->amount = $amount;
        $this->resetValidation();

        Flux::modal('settle-up-'.$this->group->getKey())->show();
    }

    public function save(RecordSettlement $action): void
    {
        $this->validate();

        try {
            $action->execute(
                actor: Auth::user(),
                group: $this->group,
                from: User::query()->findOrFail($this->from_id),
                to: User::query()->findOrFail($this->to_id),
                amount: $this->amount,
            );
        } catch (SettlementException $e) {
            $this->addError('amount', $e->getMessage());

            return;
        }

        $this->dispatch('settlement-recorded');

        Flux::modal('settle-up-'.$this->group->getKey())->close();
        Flux::toast(variant: 'success', text: __('Settlement recorded.'));

        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->reset(['to_id', 'amount']);
        $this->from_id = (int) Auth::id();
    }
}
