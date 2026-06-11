<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Group;
use App\Models\User;
use Illuminate\Notifications\Notification;

final class AddedToGroup extends Notification
{
    public function __construct(
        public readonly Group $group,
        public readonly User $actor,
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
            'type' => 'group.added',
            'group_id' => $this->group->getKey(),
            'group_name' => $this->group->name,
            'actor_id' => $this->actor->getKey(),
            'actor_name' => $this->actor->name,
        ];
    }
}
