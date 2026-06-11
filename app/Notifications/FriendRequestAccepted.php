<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Notification;

final class FriendRequestAccepted extends Notification
{
    public function __construct(
        public readonly User $accepter,
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
            'type' => 'friend_request.accepted',
            'accepter_id' => $this->accepter->getKey(),
            'accepter_name' => $this->accepter->name,
        ];
    }
}
