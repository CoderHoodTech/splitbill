<?php

declare(strict_types=1);

namespace App\Actions\Settlements;

use App\Models\Group;

/**
 * Pure (no DB writes). Computes each member's net group balance in integer rupiah.
 *
 * Convention: positive = others owe the user; negative = the user owes others.
 * Every current member appears in the result (even zero). The sum across the
 * returned array is always 0 — within a group, money is conserved.
 *
 * Balance formula per user:
 *   balance = sum(total_amount of expenses they paid)
 *           - sum(share_amount of expenses they participated in)
 *           + sum(settlements where user is `from`)   // they paid out → debt reduced
 *           - sum(settlements where user is `to`)     // they received → credit reduced
 */
final class CalculateGroupBalances
{
    /**
     * @return array<int, int> user_id => net balance (rupiah), keys ascending
     */
    public function execute(Group $group): array
    {
        $balances = [];

        foreach ($group->groupMembers()->pluck('user_id') as $userId) {
            $balances[(int) $userId] = 0;
        }

        $expenses = $group->expenses()->with('participants')->get();

        foreach ($expenses as $expense) {
            $payerId = (int) $expense->payer_id;
            $balances[$payerId] = ($balances[$payerId] ?? 0) + (int) $expense->total_amount;

            foreach ($expense->participants as $participant) {
                $uid = (int) $participant->user_id;
                $balances[$uid] = ($balances[$uid] ?? 0) - (int) $participant->share_amount;
            }
        }

        foreach ($group->settlements()->get() as $settlement) {
            $from = (int) $settlement->from_user_id;
            $to = (int) $settlement->to_user_id;
            $balances[$from] = ($balances[$from] ?? 0) + (int) $settlement->amount;
            $balances[$to] = ($balances[$to] ?? 0) - (int) $settlement->amount;
        }

        ksort($balances);

        return $balances;
    }
}
