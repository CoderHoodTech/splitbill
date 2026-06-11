<?php

declare(strict_types=1);

namespace App\Actions\Splitting;

use App\Enums\SplitMethod;
use App\Exceptions\ExpenseException;

/**
 * Splits an integer-rupiah total across participants by split method.
 *
 * Returns shares keyed by `user_id` in **ascending user_id** order. The sum of returned
 * shares is ALWAYS equal to `$totalAmount` (no rounding loss).
 *
 * - equal:   each gets floor(total/n); the remainder is distributed rupiah-by-rupiah to
 *            participants in ascending user_id order until exhausted.
 * - exact:   `$inputs[$userId]` is the literal share in rupiah. They MUST sum to
 *            `$totalAmount`; otherwise an ExpenseException is thrown (no auto-correction).
 * - percent: `$inputs[$userId]` is the percentage (0..100); they MUST sum to 100 (within
 *            a 0.01 epsilon). Each share is `floor(total * percent / 100)`; any rounding
 *            remainder is added to the single first participant in ascending user_id order.
 */
final class CalculateShares
{
    private const PERCENT_EPSILON = 0.01;

    /**
     * @param  array<int>  $participantIds  user IDs (any order; will be sorted ascending)
     * @param  array<int, int|float>|null  $inputs  user_id => value (rupiah for exact, percent for percent)
     * @return array<int, int> user_id => share_amount, ascending user_id order, summing to $totalAmount
     *
     * @throws ExpenseException
     */
    public function execute(
        SplitMethod $method,
        int $totalAmount,
        array $participantIds,
        ?array $inputs = null,
    ): array {
        if ($totalAmount < 1) {
            throw ExpenseException::totalMustBePositive();
        }

        $ids = array_values(array_unique(array_map('intval', $participantIds)));

        if (count($ids) !== count($participantIds)) {
            throw ExpenseException::duplicateParticipant();
        }

        if ($ids === []) {
            throw ExpenseException::emptyParticipants();
        }

        sort($ids, SORT_NUMERIC);

        return match ($method) {
            SplitMethod::Equal => $this->splitEqual($totalAmount, $ids),
            SplitMethod::Exact => $this->splitExact($totalAmount, $ids, $inputs ?? []),
            SplitMethod::Percent => $this->splitPercent($totalAmount, $ids, $inputs ?? []),
        };
    }

    /**
     * @param  array<int>  $ids
     * @return array<int, int>
     */
    private function splitEqual(int $total, array $ids): array
    {
        $count = count($ids);
        $base = intdiv($total, $count);
        $remainder = $total - ($base * $count);

        $shares = [];
        foreach ($ids as $index => $userId) {
            $shares[$userId] = $base + ($index < $remainder ? 1 : 0);
        }

        return $shares;
    }

    /**
     * @param  array<int>  $ids
     * @param  array<int, int|float>  $inputs
     * @return array<int, int>
     */
    private function splitExact(int $total, array $ids, array $inputs): array
    {
        $shares = [];
        $sum = 0;

        foreach ($ids as $userId) {
            if (! array_key_exists($userId, $inputs)) {
                throw ExpenseException::missingShareInput($userId);
            }

            $share = (int) $inputs[$userId];

            if ($share < 0) {
                throw ExpenseException::negativeShare();
            }

            $shares[$userId] = $share;
            $sum += $share;
        }

        if ($sum !== $total) {
            throw ExpenseException::exactSharesMismatch();
        }

        return $shares;
    }

    /**
     * @param  array<int>  $ids
     * @param  array<int, int|float>  $inputs
     * @return array<int, int>
     */
    private function splitPercent(int $total, array $ids, array $inputs): array
    {
        $percentSum = 0.0;
        $shares = [];
        $sharesSum = 0;

        foreach ($ids as $userId) {
            if (! array_key_exists($userId, $inputs)) {
                throw ExpenseException::missingShareInput($userId);
            }

            $percent = (float) $inputs[$userId];

            if ($percent < 0) {
                throw ExpenseException::negativeShare();
            }

            $percentSum += $percent;
            $share = (int) floor(($total * $percent) / 100);
            $shares[$userId] = $share;
            $sharesSum += $share;
        }

        if (abs($percentSum - 100.0) > self::PERCENT_EPSILON) {
            throw ExpenseException::percentagesMustSumTo100();
        }

        $remainder = $total - $sharesSum;

        if ($remainder !== 0) {
            $firstId = $ids[0];
            $shares[$firstId] += $remainder;
        }

        return $shares;
    }
}
