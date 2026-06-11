<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Expense;
use App\Models\ExpenseParticipant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExpenseParticipant>
 */
class ExpenseParticipantFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'expense_id' => Expense::factory(),
            'user_id' => User::factory(),
            'share_amount' => fake()->numberBetween(1_000, 100_000),
        ];
    }
}
