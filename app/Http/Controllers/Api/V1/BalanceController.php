<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Settlements\CalculateGroupBalances;
use App\Actions\Settlements\SimplifyDebts;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\BalanceResource;
use App\Http\Resources\V1\TransferResource;
use App\Models\Group;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BalanceController extends Controller
{
    public function show(
        Request $request,
        Group $group,
        CalculateGroupBalances $calculate,
        SimplifyDebts $simplify,
    ): JsonResponse {
        $this->authorize('view', $group);

        $rawBalances = $calculate->execute($group);
        $transfers = $simplify->execute($rawBalances);

        $names = User::query()
            ->whereIn('id', array_keys($rawBalances))
            ->pluck('name', 'id')
            ->all();

        $balances = [];
        foreach ($rawBalances as $userId => $balance) {
            $balances[] = [
                'user_id' => $userId,
                'name' => $names[$userId] ?? null,
                'balance' => $balance,
            ];
        }

        return response()->json([
            'balances' => BalanceResource::collection($balances)->toArray($request),
            'transfers' => TransferResource::collection($transfers)->toArray($request),
        ]);
    }
}
