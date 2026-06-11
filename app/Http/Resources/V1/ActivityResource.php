<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\Models\Activity;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Activity
 */
class ActivityResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'group_id' => $this->group_id,
            'actor_id' => $this->actor_id,
            'actor_name' => $this->whenLoaded('actor', fn () => $this->actor?->name),
            'verb' => $this->verb->value,
            'subject_type' => $this->subject_type,
            'subject_id' => $this->subject_id,
            'payload' => $this->payload ?? [],
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
