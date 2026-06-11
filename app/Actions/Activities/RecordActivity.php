<?php

declare(strict_types=1);

namespace App\Actions\Activities;

use App\Enums\ActivityVerb;
use App\Models\Activity;
use App\Models\Group;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

final class RecordActivity
{
    /**
     * Append an activity row. The optional $payload is a snapshot so we can render
     * the feed even after the subject is deleted.
     *
     * @param  array<string, mixed>  $payload
     */
    public function execute(
        User $actor,
        ActivityVerb $verb,
        ?Model $subject = null,
        ?Group $group = null,
        array $payload = [],
    ): Activity {
        return Activity::query()->create([
            'group_id' => $group?->getKey(),
            'actor_id' => $actor->getKey(),
            'verb' => $verb,
            'subject_type' => $subject !== null ? $subject->getMorphClass() : null,
            'subject_id' => $subject?->getKey(),
            'payload' => $payload,
        ]);
    }
}
