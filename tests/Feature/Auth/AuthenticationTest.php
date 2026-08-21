<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('a user can log in with valid credentials', function () {
    $user = User::factory()->create([
        'password' => 'password',
    ]);

    $this->postJson('/login', [
        'email' => $user->email,
        'password' => 'password',
    ])
        ->assertOk()
        ->assertJsonPath('user.email', $user->email);

    $this->assertAuthenticatedAs($user);
});

test('invalid credentials do not authenticate the user', function () {
    $user = User::factory()->create([
        'password' => 'password',
    ]);

    $this->postJson('/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');

    $this->assertGuest();
});

test('an authenticated user can retrieve their profile', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson('/api/user')
        ->assertOk()
        ->assertJson([
            'id' => $user->id,
            'email' => $user->email,
        ]);
});

test('a guest cannot retrieve the authenticated user endpoint', function () {
    $this->getJson('/api/user')->assertUnauthorized();
});

test('an authenticated user can log out', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/logout')
        ->assertOk()
        ->assertJson([
            'message' => 'Logged out successfully.',
        ]);

    $this->assertGuest();
});
