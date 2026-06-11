<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Shapes a single member's group balance.
 *
 * Resource expects an array: ['user_id' => int, 'name' => string, 'balance' => int].
 */
class BalanceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'user_id' => (int) $this->resource['user_id'],
            'name' => $this->resource['name'] ?? null,
            'balance' => (int) $this->resource['balance'],
            'balance_formatted' => Money::format((int) $this->resource['balance']),
        ];
    }
}
