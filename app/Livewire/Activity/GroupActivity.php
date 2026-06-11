<?php

declare(strict_types=1);

namespace App\Livewire\Activity;

use App\Models\Activity;
use App\Models\Group;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

class GroupActivity extends Component
{
    use WithPagination;

    public Group $group;

    public function mount(Group $group): void
    {
        if (! Auth::user()->can('view', $group)) {
            throw new AuthorizationException;
        }

        $this->group = $group;
    }

    /**
     * Paginated activity rows newest-first.
     *
     * @return LengthAwarePaginator<int, Activity>
     */
    #[Computed]
    public function activities(): LengthAwarePaginator
    {
        return Activity::query()
            ->where('group_id', $this->group->getKey())
            ->with('actor')
            ->latest('created_at')
            ->latest('id')
            ->paginate(15);
    }

    #[On('expense-saved')]
    #[On('settlement-recorded')]
    public function refreshActivities(): void
    {
        unset($this->activities);
        $this->resetPage();
    }

    public function render()
    {
        return view('livewire.activity.group-activity');
    }
}
