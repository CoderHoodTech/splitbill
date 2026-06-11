<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SplitMethod;
use App\Models\Expense;
use App\Models\Group;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Expense>
 */
class ExpenseFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'group_id' => Group::factory(),
            'payer_id' => User::factory(),
            'description' => fake()->sentence(3),
            'total_amount' => fake()->numberBetween(10_000, 500_000),
            'split_method' => SplitMethod::Equal,
            'expense_date' => today(),
        ];
    }
}
