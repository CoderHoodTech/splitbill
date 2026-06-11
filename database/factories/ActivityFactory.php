<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ActivityVerb;
use App\Models\Activity;
use App\Models\Group;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Activity>
 */
class ActivityFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'group_id' => Group::factory(),
            'actor_id' => User::factory(),
            'verb' => ActivityVerb::ExpenseCreated->value,
            'subject_type' => null,
            'subject_id' => null,
            'payload' => [],
            'created_at' => now(),
        ];
    }
}
