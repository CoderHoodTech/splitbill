<?php

declare(strict_types=1);

use App\Enums\SplitMethod;
use App\Models\User;

it('drives a full friend → group → expense → balances → settle flow via API token', function () {
    // ---- Register two users via the API itself ----
    $aliceRes = $this->postJson('/api/v1/register', [
        'name' => 'Alice',
        'email' => 'alice@example.test',
        'password' => 'rahasia123',
        'device_name' => 'phone',
    ])->assertCreated();
    $aliceToken = $aliceRes->json('token');
    $aliceId = $aliceRes->json('data.id');

    $bobRes = $this->postJson('/api/v1/register', [
        'name' => 'Bob',
        'email' => 'bob@example.test',
        'password' => 'rahasia123',
        'device_name' => 'phone',
    ])->assertCreated();
    $bobToken = $bobRes->json('token');
    $bobId = $bobRes->json('data.id');

    $bob = User::query()->findOrFail($bobId);

    // ---- Alice sends a friend request by Bob's code ----
    $sendRes = actingAsToken($aliceToken)
        ->postJson('/api/v1/friends/requests', ['friend_code' => $bob->friend_code])
        ->assertCreated();
    $reqId = $sendRes->json('data.id');

    // ---- Bob accepts ----
    actingAsToken($bobToken)
        ->postJson("/api/v1/friends/requests/{$reqId}/accept")
        ->assertOk();

    // ---- Alice creates a group with Bob ----
    $groupRes = actingAsToken($aliceToken)
        ->postJson('/api/v1/groups', [
            'name' => 'Trip',
            'member_ids' => [$bobId],
        ])
        ->assertCreated();
    $groupId = $groupRes->json('data.id');

    // ---- Alice logs a 100_000 expense, split equally ----
    actingAsToken($aliceToken)
        ->postJson("/api/v1/groups/{$groupId}/expenses", [
            'description' => 'Hotel',
            'total_amount' => 100_000,
            'split_method' => SplitMethod::Equal->value,
            'expense_date' => now()->toDateString(),
            'payer_id' => $aliceId,
            'participant_ids' => [$aliceId, $bobId],
        ])
        ->assertCreated();

    // ---- Check balances ----
    $balancesRes = actingAsToken($aliceToken)
        ->getJson("/api/v1/groups/{$groupId}/balances")
        ->assertOk();

    $balances = collect($balancesRes->json('balances'))->keyBy('user_id');
    expect($balances[$aliceId]['balance'])->toBe(50_000)
        ->and($balances[$bobId]['balance'])->toBe(-50_000);

    expect($balancesRes->json('transfers'))->toHaveCount(1);
    expect($balancesRes->json('transfers.0.from'))->toBe($bobId);
    expect($balancesRes->json('transfers.0.to'))->toBe($aliceId);
    expect($balancesRes->json('transfers.0.amount'))->toBe(50_000);

    // ---- Bob settles up ----
    actingAsToken($bobToken)
        ->postJson("/api/v1/groups/{$groupId}/settlements", [
            'from_user_id' => $bobId,
            'to_user_id' => $aliceId,
            'amount' => 50_000,
        ])
        ->assertCreated();

    // ---- Balances should now be zero ----
    $finalRes = actingAsToken($aliceToken)
        ->getJson("/api/v1/groups/{$groupId}/balances")
        ->assertOk();

    $finalBalances = collect($finalRes->json('balances'))->keyBy('user_id');
    expect($finalBalances[$aliceId]['balance'])->toBe(0)
        ->and($finalBalances[$bobId]['balance'])->toBe(0)
        ->and($finalRes->json('transfers'))->toBe([]);
});
