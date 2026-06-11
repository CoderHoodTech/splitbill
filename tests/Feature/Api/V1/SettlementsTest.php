<?php

declare(strict_types=1);

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Settlement;
use App\Models\User;

it('records a settlement when actor is the payer', function () {
    $owner = User::factory()->create();
    $debtor = User::factory()->create();

    $group = Group::factory()->create(['owner_id' => $owner->id]);
    GroupMember::factory()->owner()->create(['group_id' => $group->id, 'user_id' => $owner->id]);
    GroupMember::factory()->create(['group_id' => $group->id, 'user_id' => $debtor->id]);

    $response = $this->actingAs($debtor, 'sanctum')
        ->postJson("/api/v1/groups/{$group->id}/settlements", [
            'from_user_id' => $debtor->id,
            'to_user_id' => $owner->id,
            'amount' => 30_000,
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.amount', 30_000)
        ->assertJsonPath('data.amount_formatted', 'Rp30.000');

    expect(Settlement::query()->count())->toBe(1);
});

it('rejects a settlement when actor is neither payer nor recipient', function () {
    $owner = User::factory()->create();
    $debtor = User::factory()->create();
    $bystander = User::factory()->create();

    $group = Group::factory()->create(['owner_id' => $owner->id]);
    GroupMember::factory()->owner()->create(['group_id' => $group->id, 'user_id' => $owner->id]);
    GroupMember::factory()->create(['group_id' => $group->id, 'user_id' => $debtor->id]);
    GroupMember::factory()->create(['group_id' => $group->id, 'user_id' => $bystander->id]);

    $this->actingAs($bystander, 'sanctum')
        ->postJson("/api/v1/groups/{$group->id}/settlements", [
            'from_user_id' => $debtor->id,
            'to_user_id' => $owner->id,
            'amount' => 10_000,
        ])
        ->assertUnprocessable();
});

it('forbids non-members from recording settlements', function () {
    $owner = User::factory()->create();
    $debtor = User::factory()->create();

    $group = Group::factory()->create(['owner_id' => $owner->id]);
    GroupMember::factory()->owner()->create(['group_id' => $group->id, 'user_id' => $owner->id]);
    GroupMember::factory()->create(['group_id' => $group->id, 'user_id' => $debtor->id]);

    $outsider = User::factory()->create();

    $this->actingAs($outsider, 'sanctum')
        ->postJson("/api/v1/groups/{$group->id}/settlements", [
            'from_user_id' => $debtor->id,
            'to_user_id' => $owner->id,
            'amount' => 10_000,
        ])
        ->assertForbidden();
});
