<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Settlements\RecordSettlement;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\SettlementResource;
use App\Models\Group;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SettlementController extends Controller
{
    public function store(Request $request, Group $group, RecordSettlement $action): JsonResponse
    {
        $this->authorize('recordSettlement', $group);

        $data = $request->validate([
            'from_user_id' => ['required', 'integer', 'exists:users,id'],
            'to_user_id' => ['required', 'integer', 'exists:users,id'],
            'amount' => ['required', 'integer', 'min:1'],
        ]);

        $from = User::query()->findOrFail($data['from_user_id']);
        $to = User::query()->findOrFail($data['to_user_id']);

        $settlement = $action->execute(
            actor: $request->user(),
            group: $group,
            from: $from,
            to: $to,
            amount: $data['amount'],
        );

        return SettlementResource::make($settlement)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
