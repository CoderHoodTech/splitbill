<?php

declare(strict_types=1);

namespace App\Actions\Groups;

use App\Enums\GroupMemberRole;
use App\Exceptions\GroupException;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class CreateGroup
{
    /**
     * Create a group, auto-add the owner, and add any extra members.
     *
     * @param  array<int>  $memberIds  user IDs (must all be friends of $owner)
     *
     * @throws GroupException
     */
    public function execute(
        User $owner,
        string $name,
        array $memberIds = [],
        ?string $description = null,
    ): Group {
        $name = trim($name);

        if ($name === '') {
            throw GroupException::nameRequired();
        }

        $memberIds = array_values(array_unique(array_map('intval', $memberIds)));
        $memberIds = array_filter($memberIds, fn (int $id) => $id !== (int) $owner->getKey());

        foreach ($memberIds as $id) {
            $friend = User::query()->find($id);

            if ($friend === null || ! $owner->isFriendsWith($friend)) {
                throw GroupException::memberNotFriend();
            }
        }

        return DB::transaction(function () use ($owner, $name, $description, $memberIds): Group {
            $group = Group::query()->create([
                'name' => $name,
                'description' => $description !== null && trim($description) !== '' ? trim($description) : null,
                'owner_id' => $owner->getKey(),
            ]);

            GroupMember::query()->create([
                'group_id' => $group->getKey(),
                'user_id' => $owner->getKey(),
                'role' => GroupMemberRole::Owner,
            ]);

            foreach ($memberIds as $userId) {
                GroupMember::query()->create([
                    'group_id' => $group->getKey(),
                    'user_id' => $userId,
                    'role' => GroupMemberRole::Member,
                ]);
            }

            return $group->fresh();
        });
    }
}
