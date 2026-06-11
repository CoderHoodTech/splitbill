<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\FriendRequest;
use App\Models\User;
use Illuminate\Notifications\Notification;

final class FriendRequestReceived extends Notification
{
    public function __construct(
        public readonly FriendRequest $friendRequest,
        public readonly User $sender,
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
            'type' => 'friend_request.received',
            'friend_request_id' => $this->friendRequest->getKey(),
            'sender_id' => $this->sender->getKey(),
            'sender_name' => $this->sender->name,
        ];
    }
}
