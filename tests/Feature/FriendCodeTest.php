<?php

declare(strict_types=1);

use App\Actions\Users\GenerateFriendCode;
use App\Models\User;
use Illuminate\Support\Facades\DB;

test('a new user gets a friend code on creation', function () {
    $user = User::factory()->create();

    expect($user->friend_code)
        ->toBeString()
        ->toHaveLength(8)
        ->toMatch('/^[0-9A-Z]+$/');
});

test('friend codes are unique across many users', function () {
    $users = User::factory()->count(25)->create();

    $codes = $users->pluck('friend_code');

    expect($codes->unique())->toHaveCount(25)
        ->and($codes->contains(null))->toBeFalse();
});

test('an existing friend code is not regenerated on save', function () {
    $user = User::factory()->create();
    $original = $user->friend_code;

    $user->name = 'Updated Name';
    $user->save();

    expect($user->fresh()->friend_code)->toBe($original);
});

test('the action returns a unique code that does not collide with existing rows', function () {
    // Seed every possible 40-bit-suffix collision space we'd care about.
    User::factory()->count(50)->create();

    $generated = collect(range(1, 20))
        ->map(fn () => app(GenerateFriendCode::class)->execute());

    expect($generated->unique())->toHaveCount(20);

    $taken = DB::table('users')->pluck('friend_code')->all();
    foreach ($generated as $code) {
        expect($taken)->not->toContain($code);
    }
});

test('the friend code is visible on the dashboard', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee($user->friend_code);
});

test('the friend code is visible on the profile settings page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/settings/profile')
        ->assertOk()
        ->assertSee($user->friend_code);
});
