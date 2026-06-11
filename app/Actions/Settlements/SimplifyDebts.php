<?php

declare(strict_types=1);

namespace App\Actions\Settlements;

/**
 * Pure. Greedy "minimum transfers" debt simplification: each iteration pairs the
 * largest creditor (most positive balance) with the largest debtor (most negative
 * balance) and creates one transfer of `min(creditor, |debtor|)`. Continues until
 * everyone is at zero.
 *
 * Produces at most `N-1` transfers for `N` members with non-zero balances, which is
 * the lower bound for any settlement scheme. Ties (equal magnitudes) are broken by
 * ascending `user_id`, so the output is deterministic and testable.
 *
 * Input MUST sum to zero (CalculateGroupBalances guarantees that for any group).
 */
final class SimplifyDebts
{
    /**
     * @param  array<int, int>  $balances  user_id => net rupiah balance
     * @return list<array{from:int,to:int,amount:int}> transfers, in order
     */
    public function execute(array $balances): array
    {
        $creditors = [];
        $debtors = [];

        foreach ($balances as $userId => $amount) {
            $userId = (int) $userId;
            $amount = (int) $amount;

            if ($amount > 0) {
                $creditors[$userId] = $amount;
            } elseif ($amount < 0) {
                $debtors[$userId] = -$amount;
            }
        }

        $transfers = [];

        while ($creditors !== [] && $debtors !== []) {
            $creditorId = $this->pickLargestKey($creditors);
            $debtorId = $this->pickLargestKey($debtors);

            $amount = min($creditors[$creditorId], $debtors[$debtorId]);

            $transfers[] = [
                'from' => $debtorId,
                'to' => $creditorId,
                'amount' => $amount,
            ];

            $creditors[$creditorId] -= $amount;
            $debtors[$debtorId] -= $amount;

            if ($creditors[$creditorId] === 0) {
                unset($creditors[$creditorId]);
            }

            if ($debtors[$debtorId] === 0) {
                unset($debtors[$debtorId]);
            }
        }

        return $transfers;
    }

    /**
     * Picks the user_id with the largest balance; ties broken by ascending user_id.
     *
     * @param  array<int, int>  $balances  non-empty
     */
    private function pickLargestKey(array $balances): int
    {
        $bestKey = null;
        $bestValue = PHP_INT_MIN;

        foreach ($balances as $userId => $amount) {
            $userId = (int) $userId;

            if ($amount > $bestValue || ($amount === $bestValue && $userId < $bestKey)) {
                $bestKey = $userId;
                $bestValue = $amount;
            }
        }

        return (int) $bestKey;
    }
}
