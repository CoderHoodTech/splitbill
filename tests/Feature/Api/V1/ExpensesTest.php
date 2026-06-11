<?php

declare(strict_types=1);

use App\Enums\SplitMethod;
use App\Models\Expense;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;

function makeGroupWithMembers(int $extraMembers = 2): array
{
    $owner = User::factory()->create();
    $group = Group::factory()->create(['owner_id' => $owner->id]);
    GroupMember::factory()->owner()->create(['group_id' => $group->id, 'user_id' => $owner->id]);

    $members = [$owner];
    for ($i = 0; $i < $extraMembers; $i++) {
        $m = User::factory()->create();
        GroupMember::factory()->create(['group_id' => $group->id, 'user_id' => $m->id]);
        $members[] = $m;
    }

    return [$group, $members];
}

it('lists expenses to group members', function () {
    [$group, $members] = makeGroupWithMembers(1);
    [$owner, $other] = $members;

    Expense::factory()->create([
        'group_id' => $group->id,
        'payer_id' => $owner->id,
    ]);

    $response = $this->actingAs($owner, 'sanctum')->getJson("/api/v1/groups/{$group->id}/expenses");

    $response->assertOk()->assertJsonCount(1, 'data');
});

it('forbids non-members from listing expenses', function () {
    [$group] = makeGroupWithMembers();
    $outsider = User::factory()->create();

    $this->actingAs($outsider, 'sanctum')
        ->getJson("/api/v1/groups/{$group->id}/expenses")
        ->assertForbidden();
});

it('creates an equal-split expense', function () {
    [$group, $members] = makeGroupWithMembers(2);
    [$owner, $b, $c] = $members;

    $response = $this->actingAs($owner, 'sanctum')->postJson("/api/v1/groups/{$group->id}/expenses", [
        'description' => 'Makan siang',
        'total_amount' => 90_000,
        'split_method' => SplitMethod::Equal->value,
        'expense_date' => now()->toDateString(),
        'payer_id' => $owner->id,
        'participant_ids' => [$owner->id, $b->id, $c->id],
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.total_amount', 90_000)
        ->assertJsonPath('data.total_amount_formatted', 'Rp90.000')
        ->assertJsonCount(3, 'data.participants');
});

it('creates a percent-split expense and surfaces formatted money', function () {
    [$group, $members] = makeGroupWithMembers(1);
    [$owner, $b] = $members;

    $response = $this->actingAs($owner, 'sanctum')->postJson("/api/v1/groups/{$group->id}/expenses", [
        'description' => 'Bensin',
        'total_amount' => 50_000,
        'split_method' => SplitMethod::Percent->value,
        'expense_date' => now()->toDateString(),
        'payer_id' => $owner->id,
        'participant_ids' => [$owner->id, $b->id],
        'shares' => [
            (string) $owner->id => 60,
            (string) $b->id => 40,
        ],
    ]);

    $response->assertCreated();

    $shares = collect($response->json('data.participants'))->keyBy('user_id');
    expect($shares[$owner->id]['share_amount'] + $shares[$b->id]['share_amount'])->toBe(50_000);
});

it('returns 422 when participant_ids is empty', function () {
    [$group, $members] = makeGroupWithMembers(1);
    $owner = $members[0];

    $response = $this->actingAs($owner, 'sanctum')->postJson("/api/v1/groups/{$group->id}/expenses", [
        'description' => 'Makan',
        'total_amount' => 10_000,
        'split_method' => SplitMethod::Equal->value,
        'expense_date' => now()->toDateString(),
        'payer_id' => $owner->id,
        'participant_ids' => [],
    ]);

    $response->assertUnprocessable();
});

it('rejects an expense where payer is not a member', function () {
    [$group, $members] = makeGroupWithMembers(1);
    $owner = $members[0];
    $outsider = User::factory()->create();

    $response = $this->actingAs($owner, 'sanctum')->postJson("/api/v1/groups/{$group->id}/expenses", [
        'description' => 'X',
        'total_amount' => 10_000,
        'split_method' => SplitMethod::Equal->value,
        'expense_date' => now()->toDateString(),
        'payer_id' => $outsider->id,
        'participant_ids' => [$owner->id],
    ]);

    $response->assertUnprocessable();
});
