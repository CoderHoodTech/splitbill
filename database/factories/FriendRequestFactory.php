<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\FriendRequestStatus;
use App\Models\FriendRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FriendRequest>
 */
class FriendRequestFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sender_id' => User::factory(),
            'receiver_id' => User::factory(),
            'status' => FriendRequestStatus::Pending,
            'responded_at' => null,
        ];
    }

    public function accepted(): static
    {
        return $this->state(fn () => [
            'status' => FriendRequestStatus::Accepted,
            'responded_at' => now(),
        ]);
    }

    public function declined(): static
    {
        return $this->state(fn () => [
            'status' => FriendRequestStatus::Declined,
            'responded_at' => now(),
        ]);
    }
}
