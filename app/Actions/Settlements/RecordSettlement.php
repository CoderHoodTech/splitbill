<?php

declare(strict_types=1);

namespace App\Actions\Settlements;

use App\Actions\Activities\RecordActivity;
use App\Enums\ActivityVerb;
use App\Exceptions\SettlementException;
use App\Models\Group;
use App\Models\Settlement;
use App\Models\User;
use App\Notifications\SettlementReceived as SettlementReceivedNotification;

final class RecordSettlement
{
    public function __construct(private readonly RecordActivity $recordActivity) {}

    /**
     * Record that `$from` paid `$to` `$amount` rupiah inside `$group`.
     *
     * The actor must be either `$from` (debtor recording "I paid") OR `$to`
     * (creditor recording "I received"). Partial settlements are allowed — we
     * never check whether `$amount` matches the outstanding debt, because the
     * user may also pre-pay or record any agreed transfer.
     *
     * @throws SettlementException
     */
    public function execute(
        User $actor,
        Group $group,
        User $from,
        User $to,
        int $amount,
    ): Settlement {
        if ($amount < 1) {
            throw SettlementException::amountMustBePositive();
        }

        if ((int) $from->getKey() === (int) $to->getKey()) {
            throw SettlementException::fromAndToMustDiffer();
        }

        if (! $group->hasMember($from)) {
            throw SettlementException::fromNotMember();
        }

        if (! $group->hasMember($to)) {
            throw SettlementException::toNotMember();
        }

        $actorId = (int) $actor->getKey();

        if ($actorId !== (int) $from->getKey() && $actorId !== (int) $to->getKey()) {
            throw SettlementException::actorNotInvolved();
        }

        $settlement = Settlement::query()->create([
            'group_id' => $group->getKey(),
            'from_user_id' => $from->getKey(),
            'to_user_id' => $to->getKey(),
            'amount' => $amount,
            'settled_at' => now(),
        ]);

        $this->recordActivity->execute(
            actor: $actor,
            verb: ActivityVerb::SettlementRecorded,
            subject: $settlement,
            group: $group,
            payload: [
                'from_id' => $from->getKey(),
                'from_name' => $from->name,
                'to_id' => $to->getKey(),
                'to_name' => $to->name,
                'amount' => $amount,
            ],
        );

        if ((int) $actor->getKey() !== (int) $to->getKey()) {
            $to->notify(new SettlementReceivedNotification($settlement, $group, $from));
        }

        return $settlement;
    }
}
