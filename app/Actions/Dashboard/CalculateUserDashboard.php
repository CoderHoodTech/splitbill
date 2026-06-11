<?php

declare(strict_types=1);

namespace App\Actions\Dashboard;

use App\Actions\Settlements\CalculateGroupBalances;
use App\Models\User;

/**
 * Aggregates a user's position across every group into a single dashboard
 * summary. Pure read — no writes. Reuses {@see CalculateGroupBalances} so the
 * per-group math has exactly one source of truth (web + API + dashboard agree).
 *
 * Balance convention (inherited): positive = others owe the user.
 *
 * @phpstan-type GroupSummary array{id: int, name: string, members_count: int, your_balance: int}
 * @phpstan-type DashboardSummary array{
 *     total_owed_to_you: int,
 *     total_you_owe: int,
 *     net: int,
 *     groups_count: int,
 *     friends_count: int,
 *     pending_requests_count: int,
 *     groups: list<GroupSummary>,
 * }
 */
final class CalculateUserDashboard
{
    public function __construct(private CalculateGroupBalances $balances) {}

    /**
     * @return DashboardSummary
     */
    public function execute(User $user): array
    {
        $userId = (int) $user->getKey();

        $groups = $user->groups()
            ->withCount(['groupMembers as members_count'])
            ->orderBy('name')
            ->get();

        $totalOwedToYou = 0;
        $totalYouOwe = 0;
        $groupSummaries = [];

        foreach ($groups as $group) {
            $yourBalance = $this->balances->execute($group)[$userId] ?? 0;

            if ($yourBalance > 0) {
                $totalOwedToYou += $yourBalance;
            } elseif ($yourBalance < 0) {
                $totalYouOwe += -$yourBalance;
            }

            $groupSummaries[] = [
                'id' => (int) $group->getKey(),
                'name' => (string) $group->name,
                'members_count' => (int) $group->members_count,
                'your_balance' => $yourBalance,
            ];
        }

        return [
            'total_owed_to_you' => $totalOwedToYou,
            'total_you_owe' => $totalYouOwe,
            'net' => $totalOwedToYou - $totalYouOwe,
            'groups_count' => $groups->count(),
            'friends_count' => $user->friends()->count(),
            'pending_requests_count' => $user->receivedFriendRequests()->pending()->count(),
            'groups' => $groupSummaries,
        ];
    }
}
