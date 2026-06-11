<?php

declare(strict_types=1);

use App\Actions\Expenses\AddExpense;
use App\Actions\Expenses\DeleteExpense;
use App\Actions\Expenses\EditExpense;
use App\Actions\Friends\AcceptFriendRequest;
use App\Actions\Friends\SendFriendRequest;
use App\Actions\Groups\AddGroupMember;
use App\Actions\Groups\CreateGroup;
use App\Actions\Settlements\RecordSettlement;
use App\Enums\SplitMethod;
use App\Models\Friendship;
use App\Models\User;
use App\Notifications\AddedToGroup;
use App\Notifications\ExpenseAddedToYourGroup;
use App\Notifications\ExpenseDeletedInYourGroup;
use App\Notifications\ExpenseUpdatedInYourGroup;
use App\Notifications\FriendRequestAccepted;
use App\Notifications\FriendRequestReceived;
use App\Notifications\SettlementReceived;
use Illuminate\Support\Facades\Notification;

function makeFriendshipDispatch(User $a, User $b): void
{
    Friendship::query()->create(['user_id' => $a->id, 'friend_id' => $b->id]);
    Friendship::query()->create(['user_id' => $b->id, 'friend_id' => $a->id]);
}

it('notifies the receiver when a friend request is sent', function () {
    Notification::fake();
    $sender = User::factory()->create();
    $receiver = User::factory()->create(['friend_code' => 'ABC12345']);

    app(SendFriendRequest::class)->execute($sender, 'ABC12345');

    Notification::assertSentTo($receiver, FriendRequestReceived::class);
    Notification::assertNotSentTo($sender, FriendRequestReceived::class);
});

it('notifies the sender when a friend request is accepted', function () {
    Notification::fake();
    $sender = User::factory()->create();
    $receiver = User::factory()->create(['friend_code' => 'XYZ98765']);

    $request = app(SendFriendRequest::class)->execute($sender, 'XYZ98765');
    app(AcceptFriendRequest::class)->execute($receiver, $request);

    Notification::assertSentTo($sender, FriendRequestAccepted::class);
});

it('notifies all participants except the payer when an expense is added', function () {
    Notification::fake();
    $owner = User::factory()->create();
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    makeFriendshipDispatch($owner, $alice);
    makeFriendshipDispatch($owner, $bob);
    $group = app(CreateGroup::class)->execute($owner, 'Bali', [$alice->id, $bob->id]);

    app(AddExpense::class)->execute(
        group: $group,
        payer: $owner,
        description: 'Kopi',
        totalAmount: 30_000,
        method: SplitMethod::Equal,
        expenseDate: today(),
        participantIds: [$owner->id, $alice->id, $bob->id],
    );

    Notification::assertSentTo($alice, ExpenseAddedToYourGroup::class);
    Notification::assertSentTo($bob, ExpenseAddedToYourGroup::class);
    Notification::assertNotSentTo($owner, ExpenseAddedToYourGroup::class);
});

it('notifies participants except the editor when an expense is edited', function () {
    $owner = User::factory()->create();
    $alice = User::factory()->create();
    makeFriendshipDispatch($owner, $alice);
    $group = app(CreateGroup::class)->execute($owner, 'Bali', [$alice->id]);

    $expense = app(AddExpense::class)->execute(
        group: $group,
        payer: $owner,
        description: 'Kopi',
        totalAmount: 30_000,
        method: SplitMethod::Equal,
        expenseDate: today(),
        participantIds: [$owner->id, $alice->id],
    );

    Notification::fake();

    app(EditExpense::class)->execute(
        actor: $owner,
        expense: $expense,
        payer: $owner,
        description: 'Kopi Susu',
        totalAmount: 40_000,
        method: SplitMethod::Equal,
        expenseDate: today(),
        participantIds: [$owner->id, $alice->id],
    );

    Notification::assertSentTo($alice, ExpenseUpdatedInYourGroup::class);
    Notification::assertNotSentTo($owner, ExpenseUpdatedInYourGroup::class);
});

it('notifies participants except the deleter when an expense is deleted', function () {
    $owner = User::factory()->create();
    $alice = User::factory()->create();
    makeFriendshipDispatch($owner, $alice);
    $group = app(CreateGroup::class)->execute($owner, 'Bali', [$alice->id]);

    $expense = app(AddExpense::class)->execute(
        group: $group,
        payer: $owner,
        description: 'Kopi',
        totalAmount: 30_000,
        method: SplitMethod::Equal,
        expenseDate: today(),
        participantIds: [$owner->id, $alice->id],
    );

    Notification::fake();

    app(DeleteExpense::class)->execute($owner, $expense);

    Notification::assertSentTo($alice, ExpenseDeletedInYourGroup::class);
    Notification::assertNotSentTo($owner, ExpenseDeletedInYourGroup::class);
});

it('notifies the recipient of a settlement they did not record', function () {
    Notification::fake();
    $owner = User::factory()->create();
    $alice = User::factory()->create();
    makeFriendshipDispatch($owner, $alice);
    $group = app(CreateGroup::class)->execute($owner, 'Bali', [$alice->id]);

    app(RecordSettlement::class)->execute($owner, $group, $owner, $alice, 20_000);

    Notification::assertSentTo($alice, SettlementReceived::class);
});

it('does not notify the recipient when they recorded the settlement themselves', function () {
    Notification::fake();
    $owner = User::factory()->create();
    $alice = User::factory()->create();
    makeFriendshipDispatch($owner, $alice);
    $group = app(CreateGroup::class)->execute($owner, 'Bali', [$alice->id]);

    app(RecordSettlement::class)->execute($alice, $group, $owner, $alice, 20_000);

    Notification::assertNothingSentTo($alice);
});

it('notifies the new member when they are added to a group', function () {
    Notification::fake();
    $owner = User::factory()->create();
    $alice = User::factory()->create();
    makeFriendshipDispatch($owner, $alice);
    $group = app(CreateGroup::class)->execute($owner, 'Bali');

    app(AddGroupMember::class)->execute($owner, $group, $alice);

    Notification::assertSentTo($alice, AddedToGroup::class);
});
