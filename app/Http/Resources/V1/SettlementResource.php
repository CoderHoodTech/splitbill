<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\Models\Settlement;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Settlement
 */
class SettlementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'group_id' => $this->group_id,
            'from_user_id' => $this->from_user_id,
            'to_user_id' => $this->to_user_id,
            'amount' => (int) $this->amount,
            'amount_formatted' => Money::format((int) $this->amount),
            'settled_at' => $this->settled_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
