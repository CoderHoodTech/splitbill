<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\Models\ExpenseParticipant;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ExpenseParticipant
 */
class ExpenseParticipantResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'user_id' => $this->user_id,
            'share_amount' => (int) $this->share_amount,
            'share_amount_formatted' => Money::format((int) $this->share_amount),
        ];
    }
}
