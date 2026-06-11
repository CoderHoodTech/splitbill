<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Money helper for IDR (Indonesian Rupiah).
 *
 * Per project rule (00-project-context.md): all money is stored as INTEGER rupiah
 * (no decimals). IDR uses EU-style formatting where "." is the thousands separator.
 *
 * Examples:
 *   Money::format(50000)    → "Rp50.000"
 *   Money::format(1250000)  → "Rp1.250.000"
 *   Money::format(0)        → "Rp0"
 */
final class Money
{
    /**
     * Format an integer rupiah amount to the Indonesian convention ("Rp50.000").
     *
     * @param  int  $rupiah  amount in whole rupiah (no cents)
     */
    public static function format(int $rupiah): string
    {
        $sign = $rupiah < 0 ? '-' : '';
        $abs = abs($rupiah);

        return $sign.'Rp'.number_format($abs, 0, ',', '.');
    }
}
