<?php

declare(strict_types=1);

use App\Actions\Expenses\AddExpense;
use App\Enums\SplitMethod;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;
use Carbon\CarbonImmutable;

it('returns balances + simplified transfers for a group', function () {
    $owner = User::factory()->create(['name' => 'Owner']);
    $b = User::factory()->create(['name' => 'Bagas']);
    $c = User::factory()->create(['name' => 'Citra']);

    $group = Group::factory()->create(['owner_id' => $owner->id]);
    GroupMember::factory()->owner()->create(['group_id' => $group->id, 'user_id' => $owner->id]);
    GroupMember::factory()->create(['group_id' => $group->id, 'user_id' => $b->id]);
    GroupMember::factory()->create(['group_id' => $group->id, 'user_id' => $c->id]);

    // Owner paid 90_000 split equally among 3 → each owes 30_000.
    app(AddExpense::class)->execute(
        group: $group,
        payer: $owner,
        description: 'Makan',
        totalAmount: 90_000,
        method: SplitMethod::Equal,
        expenseDate: CarbonImmutable::now(),
        participantIds: [$owner->id, $b->id, $c->id],
    );

    $response = $this->actingAs($owner, 'sanctum')->getJson("/api/v1/groups/{$group->id}/balances");

    $response->assertOk();

    $balances = collect($response->json('balances'))->keyBy('user_id');
    expect($balances[$owner->id]['balance'])->toBe(60_000)
        ->and($balances[$b->id]['balance'])->toBe(-30_000)
        ->and($balances[$c->id]['balance'])->toBe(-30_000)
        ->and($balances[$owner->id]['balance_formatted'])->toBe('Rp60.000')
        ->and($balances[$b->id]['balance_formatted'])->toBe('-Rp30.000');

    $transfers = $response->json('transfers');
    expect($transfers)->toHaveCount(2);

    $sum = array_sum(array_column($transfers, 'amount'));
    expect($sum)->toBe(60_000);
});

it('forbids non-members from reading balances', function () {
    $owner = User::factory()->create();
    $group = Group::factory()->create(['owner_id' => $owner->id]);
    GroupMember::factory()->owner()->create(['group_id' => $group->id, 'user_id' => $owner->id]);

    $outsider = User::factory()->create();

    $this->actingAs($outsider, 'sanctum')
        ->getJson("/api/v1/groups/{$group->id}/balances")
        ->assertForbidden();
});
