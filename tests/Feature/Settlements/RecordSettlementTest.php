<?php

declare(strict_types=1);

use App\Actions\Groups\CreateGroup;
use App\Actions\Settlements\RecordSettlement;
use App\Exceptions\SettlementException;
use App\Models\Friendship;
use App\Models\Settlement;
use App\Models\User;

beforeEach(function () {
    $this->createGroup = app(CreateGroup::class);
    $this->record = app(RecordSettlement::class);
});

function friendsForRecordSettlement(User $a, User $b): void
{
    Friendship::query()->create(['user_id' => $a->id, 'friend_id' => $b->id]);
    Friendship::query()->create(['user_id' => $b->id, 'friend_id' => $a->id]);
}

it('records a settlement between two group members', function () {
    $owner = User::factory()->create();
    $alice = User::factory()->create();
    friendsForRecordSettlement($owner, $alice);
    $group = $this->createGroup->execute($owner, 'Bali', [$alice->id]);

    $settlement = $this->record->execute(
        actor: $alice,
        group: $group,
        from: $alice,
        to: $owner,
        amount: 25_000,
    );

    expect($settlement)->toBeInstanceOf(Settlement::class)
        ->and((int) $settlement->amount)->toBe(25_000)
        ->and((int) $settlement->from_user_id)->toBe($alice->id)
        ->and((int) $settlement->to_user_id)->toBe($owner->id)
        ->and((int) $settlement->group_id)->toBe($group->id);
});

it('allows the creditor to record the settlement (I received money)', function () {
    $owner = User::factory()->create();
    $alice = User::factory()->create();
    friendsForRecordSettlement($owner, $alice);
    $group = $this->createGroup->execute($owner, 'Bali', [$alice->id]);

    $settlement = $this->record->execute(
        actor: $owner,
        group: $group,
        from: $alice,
        to: $owner,
        amount: 25_000,
    );

    expect((int) $settlement->amount)->toBe(25_000);
});

it('rejects a zero or negative amount', function () {
    $owner = User::factory()->create();
    $alice = User::factory()->create();
    friendsForRecordSettlement($owner, $alice);
    $group = $this->createGroup->execute($owner, 'Bali', [$alice->id]);

    expect(fn () => $this->record->execute($alice, $group, $alice, $owner, 0))
        ->toThrow(SettlementException::class);
});

it('rejects when from and to are the same person', function () {
    $owner = User::factory()->create();
    $group = $this->createGroup->execute($owner, 'Solo');

    expect(fn () => $this->record->execute($owner, $group, $owner, $owner, 10_000))
        ->toThrow(SettlementException::class);
});

it('rejects when the payer is not a group member', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $group = $this->createGroup->execute($owner, 'Bali');

    expect(fn () => $this->record->execute($owner, $group, $stranger, $owner, 10_000))
        ->toThrow(SettlementException::class);
});

it('rejects when the recipient is not a group member', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $group = $this->createGroup->execute($owner, 'Bali');

    expect(fn () => $this->record->execute($owner, $group, $owner, $stranger, 10_000))
        ->toThrow(SettlementException::class);
});

it('rejects when the actor is not the payer or the recipient', function () {
    $owner = User::factory()->create();
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    friendsForRecordSettlement($owner, $alice);
    friendsForRecordSettlement($owner, $bob);
    $group = $this->createGroup->execute($owner, 'Bali', [$alice->id, $bob->id]);

    expect(fn () => $this->record->execute($bob, $group, $alice, $owner, 10_000))
        ->toThrow(SettlementException::class);
});

it('allows a partial settlement amount', function () {
    $owner = User::factory()->create();
    $alice = User::factory()->create();
    friendsForRecordSettlement($owner, $alice);
    $group = $this->createGroup->execute($owner, 'Bali', [$alice->id]);

    // No existing debt — we still allow the row because users may pre-pay.
    $settlement = $this->record->execute($alice, $group, $alice, $owner, 5_000);

    expect((int) $settlement->amount)->toBe(5_000);
});
