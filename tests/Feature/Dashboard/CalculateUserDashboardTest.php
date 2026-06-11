<?php

declare(strict_types=1);

use App\Actions\Dashboard\CalculateUserDashboard;
use App\Actions\Expenses\AddExpense;
use App\Actions\Groups\CreateGroup;
use App\Enums\FriendRequestStatus;
use App\Enums\SplitMethod;
use App\Models\Friendship;
use App\Models\User;

beforeEach(function () {
    $this->dashboard = app(CalculateUserDashboard::class);
    $this->createGroup = app(CreateGroup::class);
    $this->addExpense = app(AddExpense::class);
});

function befriendForDashboard(User $a, User $b): void
{
    Friendship::query()->create(['user_id' => $a->id, 'friend_id' => $b->id]);
    Friendship::query()->create(['user_id' => $b->id, 'friend_id' => $a->id]);
}

it('reports an empty summary for a brand-new user', function () {
    $summary = $this->dashboard->execute(User::factory()->create());

    expect($summary['groups_count'])->toBe(0)
        ->and($summary['friends_count'])->toBe(0)
        ->and($summary['pending_requests_count'])->toBe(0)
        ->and($summary['total_owed_to_you'])->toBe(0)
        ->and($summary['total_you_owe'])->toBe(0)
        ->and($summary['net'])->toBe(0)
        ->and($summary['groups'])->toBe([]);
});

it('aggregates what you are owed and what you owe across groups', function () {
    $me = User::factory()->create();
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    befriendForDashboard($me, $alice);
    befriendForDashboard($me, $bob);

    // Group A: I paid 100k split equally with Alice → Alice owes me 50k.
    $groupA = $this->createGroup->execute($me, 'Trip A', [$alice->id]);
    $this->addExpense->execute(
        group: $groupA,
        payer: $me,
        description: 'Bensin',
        totalAmount: 100_000,
        method: SplitMethod::Equal,
        expenseDate: today(),
        participantIds: [$me->id, $alice->id],
    );

    // Group B: Bob paid 80k split equally with me → I owe Bob 40k.
    $groupB = $this->createGroup->execute($bob, 'Trip B', [$me->id]);
    $this->addExpense->execute(
        group: $groupB,
        payer: $bob,
        description: 'Hotel',
        totalAmount: 80_000,
        method: SplitMethod::Equal,
        expenseDate: today(),
        participantIds: [$me->id, $bob->id],
    );

    $summary = $this->dashboard->execute($me);

    expect($summary['total_owed_to_you'])->toBe(50_000)
        ->and($summary['total_you_owe'])->toBe(40_000)
        ->and($summary['net'])->toBe(10_000)
        ->and($summary['groups_count'])->toBe(2)
        ->and($summary['friends_count'])->toBe(2);

    $byName = collect($summary['groups'])->keyBy('name');

    expect($byName['Trip A']['your_balance'])->toBe(50_000)
        ->and($byName['Trip A']['members_count'])->toBe(2)
        ->and($byName['Trip B']['your_balance'])->toBe(-40_000);
});

it('counts only pending incoming friend requests', function () {
    $me = User::factory()->create();
    User::factory()->create()->sentFriendRequests()->create([
        'receiver_id' => $me->id,
        'status' => FriendRequestStatus::Pending,
    ]);

    expect($this->dashboard->execute($me)['pending_requests_count'])->toBe(1);
});
