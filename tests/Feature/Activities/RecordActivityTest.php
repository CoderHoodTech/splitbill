<?php

declare(strict_types=1);

use App\Actions\Activities\RecordActivity;
use App\Actions\Expenses\AddExpense;
use App\Actions\Expenses\DeleteExpense;
use App\Actions\Expenses\EditExpense;
use App\Actions\Friends\AcceptFriendRequest;
use App\Actions\Groups\AddGroupMember;
use App\Actions\Groups\CreateGroup;
use App\Actions\Groups\RemoveGroupMember;
use App\Actions\Settlements\RecordSettlement;
use App\Enums\ActivityVerb;
use App\Enums\SplitMethod;
use App\Models\Activity;
use App\Models\FriendRequest;
use App\Models\Friendship;
use App\Models\User;

function makeFriendsActivity(User $a, User $b): void
{
    Friendship::query()->create(['user_id' => $a->id, 'friend_id' => $b->id]);
    Friendship::query()->create(['user_id' => $b->id, 'friend_id' => $a->id]);
}

it('appends an activity row through the action', function () {
    $actor = User::factory()->create();

    $activity = app(RecordActivity::class)->execute(
        actor: $actor,
        verb: ActivityVerb::ExpenseCreated,
        payload: ['note' => 'manual'],
    );

    expect($activity)->toBeInstanceOf(Activity::class)
        ->and($activity->verb)->toBe(ActivityVerb::ExpenseCreated)
        ->and($activity->actor_id)->toBe($actor->id)
        ->and($activity->group_id)->toBeNull()
        ->and($activity->payload)->toMatchArray(['note' => 'manual']);
});

it('records an expense.created activity when an expense is added', function () {
    $owner = User::factory()->create();
    $alice = User::factory()->create();
    makeFriendsActivity($owner, $alice);
    $group = app(CreateGroup::class)->execute($owner, 'Bali', [$alice->id]);

    $expense = app(AddExpense::class)->execute(
        group: $group,
        payer: $owner,
        description: 'Nasi Padang',
        totalAmount: 50_000,
        method: SplitMethod::Equal,
        expenseDate: today(),
        participantIds: [$owner->id, $alice->id],
    );

    $activity = Activity::query()->where('verb', ActivityVerb::ExpenseCreated->value)->latest('id')->first();

    expect($activity)->not->toBeNull()
        ->and($activity->group_id)->toBe($group->id)
        ->and($activity->actor_id)->toBe($owner->id)
        ->and($activity->subject_id)->toBe($expense->id)
        ->and($activity->subject_type)->toBe($expense->getMorphClass())
        ->and($activity->payload['description'])->toBe('Nasi Padang')
        ->and($activity->payload['total_amount'])->toBe(50_000);
});

it('records an expense.updated activity when an expense is edited', function () {
    $owner = User::factory()->create();
    $alice = User::factory()->create();
    makeFriendsActivity($owner, $alice);
    $group = app(CreateGroup::class)->execute($owner, 'Bali', [$alice->id]);

    $expense = app(AddExpense::class)->execute(
        group: $group,
        payer: $owner,
        description: 'Bensin',
        totalAmount: 30_000,
        method: SplitMethod::Equal,
        expenseDate: today(),
        participantIds: [$owner->id, $alice->id],
    );

    Activity::query()->delete();

    app(EditExpense::class)->execute(
        actor: $owner,
        expense: $expense,
        payer: $owner,
        description: 'Bensin Pertalite',
        totalAmount: 40_000,
        method: SplitMethod::Equal,
        expenseDate: today(),
        participantIds: [$owner->id, $alice->id],
    );

    $activity = Activity::query()->where('verb', ActivityVerb::ExpenseUpdated->value)->first();

    expect($activity)->not->toBeNull()
        ->and($activity->payload['description'])->toBe('Bensin Pertalite')
        ->and($activity->payload['total_amount'])->toBe(40_000);
});

it('records an expense.deleted activity with a snapshot when an expense is deleted', function () {
    $owner = User::factory()->create();
    $alice = User::factory()->create();
    makeFriendsActivity($owner, $alice);
    $group = app(CreateGroup::class)->execute($owner, 'Bali', [$alice->id]);

    $expense = app(AddExpense::class)->execute(
        group: $group,
        payer: $owner,
        description: 'Kopi',
        totalAmount: 25_000,
        method: SplitMethod::Equal,
        expenseDate: today(),
        participantIds: [$owner->id, $alice->id],
    );

    Activity::query()->delete();

    app(DeleteExpense::class)->execute($owner, $expense);

    $activity = Activity::query()->where('verb', ActivityVerb::ExpenseDeleted->value)->first();

    expect($activity)->not->toBeNull()
        ->and($activity->payload['description'])->toBe('Kopi')
        ->and($activity->payload['total_amount'])->toBe(25_000)
        ->and($activity->subject_id)->toBeNull();
});

it('records a settlement.recorded activity when a settlement is recorded', function () {
    $owner = User::factory()->create();
    $alice = User::factory()->create();
    makeFriendsActivity($owner, $alice);
    $group = app(CreateGroup::class)->execute($owner, 'Bali', [$alice->id]);

    app(RecordSettlement::class)->execute($owner, $group, $owner, $alice, 15_000);

    $activity = Activity::query()->where('verb', ActivityVerb::SettlementRecorded->value)->first();

    expect($activity)->not->toBeNull()
        ->and($activity->payload['amount'])->toBe(15_000)
        ->and($activity->payload['from_id'])->toBe($owner->id)
        ->and($activity->payload['to_id'])->toBe($alice->id);
});

it('records a member.added activity when a member is added to the group', function () {
    $owner = User::factory()->create();
    $alice = User::factory()->create();
    makeFriendsActivity($owner, $alice);
    $group = app(CreateGroup::class)->execute($owner, 'Bali');

    Activity::query()->delete();

    app(AddGroupMember::class)->execute($owner, $group, $alice);

    $activity = Activity::query()->where('verb', ActivityVerb::MemberAdded->value)->first();

    expect($activity)->not->toBeNull()
        ->and($activity->group_id)->toBe($group->id)
        ->and($activity->payload['member_id'])->toBe($alice->id);
});

it('records a member.removed activity when a member is removed', function () {
    $owner = User::factory()->create();
    $alice = User::factory()->create();
    makeFriendsActivity($owner, $alice);
    $group = app(CreateGroup::class)->execute($owner, 'Bali', [$alice->id]);

    Activity::query()->delete();

    app(RemoveGroupMember::class)->execute($owner, $group, $alice);

    $activity = Activity::query()->where('verb', ActivityVerb::MemberRemoved->value)->first();

    expect($activity)->not->toBeNull()
        ->and($activity->payload['member_id'])->toBe($alice->id);
});

it('does not record an activity for friend requests (those live as user notifications)', function () {
    $sender = User::factory()->create();
    $receiver = User::factory()->create();
    $request = FriendRequest::factory()->create([
        'sender_id' => $sender->id,
        'receiver_id' => $receiver->id,
    ]);

    app(AcceptFriendRequest::class)->execute($receiver, $request);

    expect(Activity::query()->count())->toBe(0);
});
