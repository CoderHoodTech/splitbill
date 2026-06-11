<?php

use App\Actions\Expenses\AddExpense;
use App\Actions\Groups\CreateGroup;
use App\Enums\SplitMethod;
use App\Livewire\Dashboard;
use App\Models\Friendship;
use App\Models\User;
use Livewire\Livewire;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('the dashboard shows the friend code and balance summary', function () {
    $me = User::factory()->create();
    $alice = User::factory()->create();
    Friendship::query()->create(['user_id' => $me->id, 'friend_id' => $alice->id]);
    Friendship::query()->create(['user_id' => $alice->id, 'friend_id' => $me->id]);

    $group = app(CreateGroup::class)->execute($me, 'Trip Bali', [$alice->id]);
    app(AddExpense::class)->execute(
        group: $group,
        payer: $me,
        description: 'Bensin',
        totalAmount: 100_000,
        method: SplitMethod::Equal,
        expenseDate: today(),
        participantIds: [$me->id, $alice->id],
    );

    $this->actingAs($me);

    Livewire::test(Dashboard::class)
        ->assertSee($me->friend_code)
        ->assertSee('Trip Bali')
        ->assertSee('Rp50.000')   // owed to me in this group
        ->assertSee("You're owed");
});

test('the dashboard shows an empty state when the user has no groups', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(Dashboard::class)
        ->assertSee("You're not in any group yet.")
        ->assertSee('Create your first group');
});
