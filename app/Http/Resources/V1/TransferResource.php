<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Shapes a single simplified-debt transfer.
 *
 * Resource expects an array: ['from' => int, 'to' => int, 'amount' => int].
 */
class TransferResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'from' => (int) $this->resource['from'],
            'to' => (int) $this->resource['to'],
            'amount' => (int) $this->resource['amount'],
            'amount_formatted' => Money::format((int) $this->resource['amount']),
        ];
    }
}
