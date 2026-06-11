<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\Models\Group;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Group
 */
class GroupResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'owner_id' => $this->owner_id,
            'members' => UserResource::collection($this->whenLoaded('members')),
            'members_count' => $this->whenCounted('members'),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
