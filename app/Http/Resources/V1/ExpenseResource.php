<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\Models\Expense;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Expense
 */
class ExpenseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'group_id' => $this->group_id,
            'payer_id' => $this->payer_id,
            'description' => $this->description,
            'total_amount' => (int) $this->total_amount,
            'total_amount_formatted' => Money::format((int) $this->total_amount),
            'split_method' => $this->split_method->value,
            'expense_date' => $this->expense_date?->toDateString(),
            'participants' => ExpenseParticipantResource::collection($this->whenLoaded('participants')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
