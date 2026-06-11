<?php

declare(strict_types=1);

namespace App\Actions\Groups;

use App\Actions\Activities\RecordActivity;
use App\Enums\ActivityVerb;
use App\Exceptions\GroupException;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;

final class RemoveGroupMember
{
    public function __construct(private readonly RecordActivity $recordActivity) {}

    /**
     * Remove a member from the group. Owner-only.
     *
     * NOTE: TODO hook for "no removal if nonzero balance" lives here. Phase 04 will
     * compute balances; the full leave/archive/delete workflow is tracked in BL-004.
     *
     * @throws GroupException
     */
    public function execute(User $actor, Group $group, User $member): void
    {
        if (! $group->isOwnedBy($actor)) {
            throw GroupException::notOwner();
        }

        if ($group->isOwnedBy($member)) {
            throw GroupException::cannotRemoveOwner();
        }

        $row = GroupMember::query()
            ->where('group_id', $group->getKey())
            ->where('user_id', $member->getKey())
            ->first();

        if ($row === null) {
            throw GroupException::memberNotInGroup();
        }

        // TODO(Phase 04 / BL-004): block removal if $member has a nonzero balance in
        // the group. For now Phase 03 only ships create/add/remove without balance gating.

        $row->delete();

        $this->recordActivity->execute(
            actor: $actor,
            verb: ActivityVerb::MemberRemoved,
            subject: $member,
            group: $group,
            payload: [
                'member_id' => $member->getKey(),
                'member_name' => $member->name,
            ],
        );
    }
}
