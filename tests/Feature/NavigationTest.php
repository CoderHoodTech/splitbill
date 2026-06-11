<?php

declare(strict_types=1);

use App\Models\User;

it('redirects guests away from friends index', function () {
    $this->get(route('friends.index'))->assertRedirect(route('login'));
});

it('redirects guests away from groups index', function () {
    $this->get(route('groups.index'))->assertRedirect(route('login'));
});

it('allows authenticated users to see friends placeholder', function () {
    $this->actingAs(User::factory()->create());
    $this->get(route('friends.index'))->assertOk()->assertSee('Friends');
});

it('allows authenticated users to see groups placeholder', function () {
    $this->actingAs(User::factory()->create());
    $this->get(route('groups.index'))->assertOk()->assertSee('Groups');
});

it('dashboard greets the authenticated user by name', function () {
    $user = User::factory()->create(['name' => 'Okky']);
    $this->actingAs($user);
    $this->get(route('dashboard'))->assertOk()->assertSee('Okky');
});
