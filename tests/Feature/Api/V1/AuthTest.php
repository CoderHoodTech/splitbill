<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('registers a new user and returns a token', function () {
    $response = $this->postJson('/api/v1/register', [
        'name' => 'Andi Pratama',
        'email' => 'andi@example.test',
        'password' => 'rahasia123',
        'device_name' => 'iPhone 15',
    ]);

    $response->assertCreated()
        ->assertJsonStructure(['data' => ['id', 'name', 'friend_code', 'email'], 'token']);

    expect(User::query()->where('email', 'andi@example.test')->exists())->toBeTrue();
});

it('rejects registration with a duplicate email', function () {
    User::factory()->create(['email' => 'taken@example.test']);

    $response = $this->postJson('/api/v1/register', [
        'name' => 'Andi',
        'email' => 'taken@example.test',
        'password' => 'rahasia123',
        'device_name' => 'iPhone',
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors(['email']);
});

it('logs in with valid credentials and issues a token', function () {
    $user = User::factory()->create([
        'email' => 'budi@example.test',
        'password' => Hash::make('rahasia123'),
    ]);

    $response = $this->postJson('/api/v1/login', [
        'email' => 'budi@example.test',
        'password' => 'rahasia123',
        'device_name' => 'Android',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonStructure(['data' => ['id', 'name', 'friend_code', 'email'], 'token']);

    expect($response->json('token'))->toBeString()->not->toBeEmpty();
});

it('rejects login with wrong password', function () {
    User::factory()->create([
        'email' => 'budi@example.test',
        'password' => Hash::make('correct-password'),
    ]);

    $response = $this->postJson('/api/v1/login', [
        'email' => 'budi@example.test',
        'password' => 'wrong-password',
        'device_name' => 'Android',
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors(['email']);
});

it('returns the authenticated user from /me', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/me');

    $response->assertOk()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.email', $user->email)
        ->assertJsonPath('data.friend_code', $user->friend_code);
});

it('rejects /me without a token', function () {
    $this->getJson('/api/v1/me')->assertUnauthorized();
});

it('revokes the current token on logout', function () {
    $user = User::factory()->create();
    $token = $user->createToken('phone')->plainTextToken;

    actingAsToken($token)
        ->postJson('/api/v1/logout')
        ->assertOk();

    actingAsToken($token)
        ->getJson('/api/v1/me')
        ->assertUnauthorized();
});
