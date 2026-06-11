<?php

declare(strict_types=1);

namespace App\Livewire\Groups;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class GroupList extends Component
{
    #[On('group-created')]
    public function refresh(): void
    {
        unset($this->groups);
    }

    /**
     * Groups the authenticated user is a member of, with member counts.
     */
    #[Computed]
    public function groups(): Collection
    {
        return Auth::user()
            ->groups()
            ->withCount(['groupMembers as members_count'])
            ->orderBy('name')
            ->get();
    }
}
