<?php

declare(strict_types=1);

namespace App\Livewire\Groups;

use App\Actions\Groups\CreateGroup as CreateGroupAction;
use App\Exceptions\GroupException;
use App\Models\User;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Validate;
use Livewire\Component;

class CreateGroup extends Component
{
    #[Validate('required|string|min:2|max:100')]
    public string $name = '';

    #[Validate('nullable|string|max:255')]
    public string $description = '';

    /**
     * @var array<int, int>
     */
    #[Validate('array')]
    public array $selectedFriendIds = [];

    /**
     * @return Collection<int, User>
     */
    #[Computed]
    public function friends(): Collection
    {
        return Auth::user()->friends()->orderBy('name')->get();
    }

    public function save(CreateGroupAction $action): void
    {
        $this->validate();

        $friendIds = array_map('intval', $this->selectedFriendIds);

        try {
            $group = $action->execute(
                owner: Auth::user(),
                name: $this->name,
                memberIds: $friendIds,
                description: $this->description !== '' ? $this->description : null,
            );
        } catch (GroupException $e) {
            $this->addError('name', $e->getMessage());

            return;
        }

        $this->reset(['name', 'description', 'selectedFriendIds']);

        Flux::modal('create-group')->close();
        Flux::toast(variant: 'success', text: __('Group ":name" created.', ['name' => $group->name]));

        $this->dispatch('group-created');
        $this->redirectRoute('groups.show', ['group' => $group->getKey()], navigate: true);
    }
}
