<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Group;
use App\Models\Settlement;
use App\Models\User;
use Illuminate\Notifications\Notification;

final class SettlementReceived extends Notification
{
    public function __construct(
        public readonly Settlement $settlement,
        public readonly Group $group,
        public readonly User $payer,
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
            'type' => 'settlement.received',
            'settlement_id' => $this->settlement->getKey(),
            'group_id' => $this->group->getKey(),
            'group_name' => $this->group->name,
            'payer_id' => $this->payer->getKey(),
            'payer_name' => $this->payer->name,
            'amount' => (int) $this->settlement->amount,
        ];
    }
}
