<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Group;
use App\Models\Settlement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Settlement>
 */
class SettlementFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'group_id' => Group::factory(),
            'from_user_id' => User::factory(),
            'to_user_id' => User::factory(),
            'amount' => fake()->numberBetween(1_000, 100_000),
            'settled_at' => now(),
        ];
    }
}
