<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\GroupMemberRole;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GroupMember>
 */
class GroupMemberFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'group_id' => Group::factory(),
            'user_id' => User::factory(),
            'role' => GroupMemberRole::Member,
        ];
    }

    public function owner(): static
    {
        return $this->state(fn () => ['role' => GroupMemberRole::Owner]);
    }
}
