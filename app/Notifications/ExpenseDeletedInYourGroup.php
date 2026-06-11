<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Group;
use App\Models\User;
use Illuminate\Notifications\Notification;

final class ExpenseDeletedInYourGroup extends Notification
{
    /**
     * @param  array<string, mixed>  $snapshot  Snapshot of the deleted expense (subject is gone).
     */
    public function __construct(
        public readonly Group $group,
        public readonly User $actor,
        public readonly array $snapshot,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'expense.deleted',
            'group_id' => $this->group->getKey(),
            'group_name' => $this->group->name,
            'actor_id' => $this->actor->getKey(),
            'actor_name' => $this->actor->name,
            'description' => $this->snapshot['description'] ?? null,
            'total_amount' => $this->snapshot['total_amount'] ?? null,
        ];
    }
}
