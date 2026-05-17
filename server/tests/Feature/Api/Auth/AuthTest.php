<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('registers a new user and returns a token', function () {
    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'password' => 'Password!234',
        'password_confirmation' => 'Password!234',
    ]);

    $response->assertCreated()
        ->assertJsonStructure([
            'token',
            'user' => ['id', 'name', 'email', 'created_at'],
        ])
        ->assertJsonPath('user.email', 'jane@example.com')
        ->assertJsonPath('user.name', 'Jane Doe');

    expect(User::where('email', 'jane@example.com')->exists())->toBeTrue();
});

it('rejects registration with mismatched password confirmation', function () {
    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'password' => 'Password!234',
        'password_confirmation' => 'different',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['password']);
});

it('rejects registration when email is already taken', function () {
    User::factory()->create(['email' => 'taken@example.com']);

    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'Jane Doe',
        'email' => 'taken@example.com',
        'password' => 'Password!234',
        'password_confirmation' => 'Password!234',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

it('rejects registration when required fields are missing', function () {
    $response = $this->postJson('/api/v1/auth/register', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['name', 'email', 'password']);
});

it('rejects registration with invalid email format', function () {
    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'Jane Doe',
        'email' => 'not-an-email',
        'password' => 'Password!234',
        'password_confirmation' => 'Password!234',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

it('logs in with valid credentials and returns a token', function () {
    $user = User::factory()->create([
        'email' => 'login@example.com',
        'password' => Hash::make('secret-pass'),
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'login@example.com',
        'password' => 'secret-pass',
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'token',
            'user' => ['id', 'email'],
        ])
        ->assertJsonPath('user.id', $user->id)
        ->assertJsonPath('user.email', 'login@example.com');

    expect($response->json('token'))->toBeString()->not->toBeEmpty();
});

it('rejects login with wrong password', function () {
    User::factory()->create([
        'email' => 'login@example.com',
        'password' => Hash::make('secret-pass'),
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'login@example.com',
        'password' => 'wrong-pass',
    ]);

    $response->assertStatus(401);
});

it('rejects login when user does not exist', function () {
    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'missing@example.com',
        'password' => 'whatever',
    ]);

    $response->assertStatus(401);
});

it('rejects login when fields are missing', function () {
    $response = $this->postJson('/api/v1/auth/login', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email', 'password']);
});

it('replaces existing auth-token on re-login', function () {
    $user = User::factory()->create([
        'email' => 'login@example.com',
        'password' => Hash::make('secret-pass'),
    ]);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'login@example.com',
        'password' => 'secret-pass',
    ])->assertOk();

    $firstCount = $user->tokens()->where('name', 'auth-token')->count();

    $this->postJson('/api/v1/auth/login', [
        'email' => 'login@example.com',
        'password' => 'secret-pass',
    ])->assertOk();

    expect($firstCount)->toBe(1)
        ->and($user->tokens()->where('name', 'auth-token')->count())->toBe(1);
});

it('revokes the current token on logout', function () {
    $user = createAuthenticatedUser();

    $response = $this->postJson('/api/v1/auth/logout');

    $response->assertNoContent();
});

it('returns the authenticated user on /me', function () {
    $user = createAuthenticatedUser(['email' => 'me@example.com', 'name' => 'Me User']);

    $response = $this->getJson('/api/v1/auth/me');

    $response->assertOk()
        ->assertJsonPath('id', $user->id)
        ->assertJsonPath('email', 'me@example.com')
        ->assertJsonPath('name', 'Me User')
        ->assertJsonStructure(['id', 'name', 'email', 'created_at']);
});

it('rejects /me when unauthenticated', function () {
    $this->getJson('/api/v1/auth/me')->assertStatus(401);
});

it('rejects logout when unauthenticated', function () {
    $this->postJson('/api/v1/auth/logout')->assertStatus(401);
});
