<?php

declare(strict_types=1);

use App\Livewire\Friends\AddFriend;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

beforeEach(function () {
    // The default `array` cache store persists across tests within one PHPUnit
    // process, so user IDs (which reset every test under RefreshDatabase) can
    // collide. Flush the limiter cache up-front to keep counts deterministic.
    app('cache')->flush();
});

test('Livewire AddFriend blocks the 11th lookup attempt within a minute', function () {
    $sender = User::factory()->create();

    $component = Livewire::actingAs($sender)->test(AddFriend::class);

    // Burn 10 attempts against an unknown code — each returns a friendly error.
    for ($i = 0; $i < 10; $i++) {
        $component->set('friend_code', 'MISS'.str_pad((string) $i, 4, '0', STR_PAD_LEFT))
            ->call('send')
            ->assertHasErrors('friend_code');
    }

    // The 11th must be rejected by the rate limiter, not the action itself.
    $component->set('friend_code', 'STILLMISS')
        ->call('send')
        ->assertHasErrors('friend_code');

    expect(RateLimiter::tooManyAttempts('friend-code-lookup:'.$sender->id, 10))->toBeTrue();
});

test('Livewire AddFriend does not count successful sends when previously rate-limited', function () {
    $sender = User::factory()->create();
    $receiver = User::factory()->create();

    Livewire::actingAs($sender)
        ->test(AddFriend::class)
        ->set('friend_code', $receiver->friend_code)
        ->call('send')
        ->assertHasNoErrors();

    // One real send still leaves headroom for 9 more lookups.
    expect(RateLimiter::attempts('friend-code-lookup:'.$sender->id))->toBe(1);
});

test('API POST /friends/requests returns 429 after exceeding the throttle', function () {
    $sender = User::factory()->create();

    for ($i = 0; $i < 10; $i++) {
        $this->actingAs($sender, 'sanctum')->postJson('/api/v1/friends/requests', [
            'friend_code' => 'MISS'.str_pad((string) $i, 4, '0', STR_PAD_LEFT),
        ])->assertUnprocessable();
    }

    $this->actingAs($sender, 'sanctum')->postJson('/api/v1/friends/requests', [
        'friend_code' => 'STILLMISS',
    ])->assertStatus(429);
});

test('rate limit keys are per-user — other users are unaffected', function () {
    $a = User::factory()->create();
    $b = User::factory()->create();
    $target = User::factory()->create();

    // A exhausts their budget probing bogus codes.
    for ($i = 0; $i < 10; $i++) {
        $this->actingAs($a, 'sanctum')->postJson('/api/v1/friends/requests', [
            'friend_code' => 'MISS'.str_pad((string) $i, 4, '0', STR_PAD_LEFT),
        ])->assertUnprocessable();
    }

    $this->actingAs($a, 'sanctum')->postJson('/api/v1/friends/requests', [
        'friend_code' => 'STILLMISS',
    ])->assertStatus(429);

    // B is untouched and can send freely.
    $this->actingAs($b, 'sanctum')->postJson('/api/v1/friends/requests', [
        'friend_code' => $target->friend_code,
    ])->assertCreated();
});
