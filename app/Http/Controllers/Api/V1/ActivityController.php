<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\ActivityResource;
use App\Models\Activity;
use App\Models\Group;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ActivityController extends Controller
{
    public function groupIndex(Request $request, Group $group): AnonymousResourceCollection
    {
        $this->authorize('view', $group);

        $activities = Activity::query()
            ->where('group_id', $group->getKey())
            ->with('actor')
            ->latest('created_at')
            ->latest('id')
            ->paginate(20);

        return ActivityResource::collection($activities);
    }
}
