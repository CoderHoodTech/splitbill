<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $isSelf = $request->user() !== null && (int) $request->user()->getKey() === (int) $this->getKey();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'friend_code' => $this->friend_code,
            'email' => $this->when($isSelf, fn () => $this->email),
        ];
    }
}
