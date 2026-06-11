<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Expenses\AddExpense;
use App\Enums\SplitMethod;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\ExpenseResource;
use App\Models\Group;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class ExpenseController extends Controller
{
    public function index(Request $request, Group $group): AnonymousResourceCollection
    {
        $this->authorize('view', $group);

        $expenses = $group->expenses()
            ->with('participants')
            ->latest('expense_date')
            ->latest('id')
            ->get();

        return ExpenseResource::collection($expenses);
    }

    public function store(Request $request, Group $group, AddExpense $action): JsonResponse
    {
        $this->authorize('addExpense', $group);

        $data = $request->validate([
            'description' => ['required', 'string', 'max:255'],
            'total_amount' => ['required', 'integer', 'min:1'],
            'split_method' => ['required', Rule::enum(SplitMethod::class)],
            'expense_date' => ['required', 'date'],
            'payer_id' => ['required', 'integer', 'exists:users,id'],
            'participant_ids' => ['required', 'array', 'min:1'],
            'participant_ids.*' => ['integer'],
            'shares' => ['nullable', 'array'],
            'shares.*' => ['numeric'],
        ]);

        $payer = User::query()->findOrFail($data['payer_id']);

        $shareInputs = null;

        if (! empty($data['shares'])) {
            $shareInputs = [];
            foreach ($data['shares'] as $userId => $value) {
                $shareInputs[(int) $userId] = $value;
            }
        }

        $expense = $action->execute(
            group: $group,
            payer: $payer,
            description: $data['description'],
            totalAmount: $data['total_amount'],
            method: SplitMethod::from($data['split_method']),
            expenseDate: CarbonImmutable::parse($data['expense_date']),
            participantIds: $data['participant_ids'],
            shareInputs: $shareInputs,
        );

        return ExpenseResource::make($expense->load('participants'))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
