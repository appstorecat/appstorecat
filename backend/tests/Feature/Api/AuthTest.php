<?php

use App\Models\User;

test('register creates user and returns token', function () {
    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertStatus(201)
        ->assertJsonStructure(['user' => ['id', 'name', 'email'], 'token']);

    $this->assertDatabaseHas('users', ['email' => 'test@example.com']);
});

test('register fails with invalid data', function () {
    $response = $this->postJson('/api/v1/auth/register', [
        'name' => '',
        'email' => 'not-an-email',
        'password' => 'short',
    ]);

    $response->assertStatus(422);
});

test('register fails with duplicate email', function () {
    User::factory()->create(['email' => 'test@example.com']);

    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertStatus(422);
});

test('login returns token with valid credentials', function () {
    User::factory()->create([
        'email' => 'test@example.com',
        'password' => 'password123',
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'test@example.com',
        'password' => 'password123',
    ]);

    $response->assertOk()
        ->assertJsonStructure(['user' => ['id', 'name', 'email'], 'token']);
});

test('login fails with wrong password', function () {
    User::factory()->create([
        'email' => 'test@example.com',
        'password' => 'password123',
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'test@example.com',
        'password' => 'wrongpassword',
    ]);

    $response->assertStatus(401);
});

test('login fails with nonexistent email', function () {
    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'nonexistent@example.com',
        'password' => 'password123',
    ]);

    $response->assertStatus(401);
});

test('logout revokes current token', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/auth/logout');

    $response->assertNoContent();
    expect($user->tokens()->count())->toBe(0);
});

test('me returns authenticated user', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/auth/me');

    $response->assertOk()
        ->assertJsonFragment(['email' => $user->email]);
});

test('me returns 401 without token', function () {
    $response = $this->getJson('/api/v1/auth/me');

    $response->assertStatus(401);
});
