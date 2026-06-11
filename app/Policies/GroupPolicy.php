<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Group;
use App\Models\User;

class GroupPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Only members may view a group.
     */
    public function view(User $user, Group $group): bool
    {
        return $group->hasMember($user);
    }

    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Only the owner may edit group metadata or manage members.
     */
    public function update(User $user, Group $group): bool
    {
        return $group->isOwnedBy($user);
    }

    public function manageMembers(User $user, Group $group): bool
    {
        return $group->isOwnedBy($user);
    }

    public function addExpense(User $user, Group $group): bool
    {
        return $group->hasMember($user);
    }

    public function recordSettlement(User $user, Group $group): bool
    {
        return $group->hasMember($user);
    }

    public function delete(User $user, Group $group): bool
    {
        return $group->isOwnedBy($user);
    }
}
