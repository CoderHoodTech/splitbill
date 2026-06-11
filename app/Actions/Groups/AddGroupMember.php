<?php

declare(strict_types=1);

namespace App\Actions\Groups;

use App\Actions\Activities\RecordActivity;
use App\Enums\ActivityVerb;
use App\Enums\GroupMemberRole;
use App\Exceptions\GroupException;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;
use App\Notifications\AddedToGroup;

final class AddGroupMember
{
    public function __construct(private readonly RecordActivity $recordActivity) {}

    /**
     * Add a friend of the owner to the group as a member.
     *
     * @throws GroupException
     */
    public function execute(User $actor, Group $group, User $newMember): GroupMember
    {
        if (! $group->isOwnedBy($actor)) {
            throw GroupException::notOwner();
        }

        if ((int) $newMember->getKey() !== (int) $actor->getKey() && ! $actor->isFriendsWith($newMember)) {
            throw GroupException::memberNotFriend();
        }

        if ($group->hasMember($newMember)) {
            throw GroupException::alreadyMember();
        }

        $member = GroupMember::query()->create([
            'group_id' => $group->getKey(),
            'user_id' => $newMember->getKey(),
            'role' => GroupMemberRole::Member,
        ]);

        $this->recordActivity->execute(
            actor: $actor,
            verb: ActivityVerb::MemberAdded,
            subject: $newMember,
            group: $group,
            payload: [
                'member_id' => $newMember->getKey(),
                'member_name' => $newMember->name,
            ],
        );

        if ((int) $newMember->getKey() !== (int) $actor->getKey()) {
            $newMember->notify(new AddedToGroup($group, $actor));
        }

        return $member;
    }
}
