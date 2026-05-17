<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('returns the authenticated user profile', function () {
    $user = createAuthenticatedUser(['email' => 'profile@example.com', 'name' => 'Profile User']);

    $response = $this->getJson('/api/v1/account/profile');

    $response->assertOk()
        ->assertJsonStructure(['id', 'name', 'email', 'email_verified_at', 'created_at'])
        ->assertJsonPath('id', $user->id)
        ->assertJsonPath('email', 'profile@example.com')
        ->assertJsonPath('name', 'Profile User');
});

it('rejects profile show when unauthenticated', function () {
    $this->getJson('/api/v1/account/profile')->assertStatus(401);
});

it('updates name and email on profile', function () {
    $user = createAuthenticatedUser(['email' => 'old@example.com', 'name' => 'Old Name']);

    $response = $this->patchJson('/api/v1/account/profile', [
        'name' => 'New Name',
        'email' => 'new@example.com',
    ]);

    $response->assertOk()
        ->assertJsonPath('name', 'New Name')
        ->assertJsonPath('email', 'new@example.com');

    $user->refresh();
    expect($user->name)->toBe('New Name')
        ->and($user->email)->toBe('new@example.com')
        ->and($user->email_verified_at)->toBeNull();
});

it('keeps email verification when only name changes', function () {
    $user = createAuthenticatedUser(['email' => 'same@example.com', 'name' => 'Old Name']);
    $originalVerifiedAt = $user->email_verified_at;

    $response = $this->patchJson('/api/v1/account/profile', [
        'name' => 'Updated Name',
        'email' => 'same@example.com',
    ]);

    $response->assertOk();

    $user->refresh();
    expect($user->email_verified_at)->not->toBeNull()
        ->and($user->email_verified_at?->toIso8601String())->toBe($originalVerifiedAt?->toIso8601String());
});

it('rejects profile update with missing fields', function () {
    createAuthenticatedUser();

    $response = $this->patchJson('/api/v1/account/profile', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['name', 'email']);
});

it('rejects profile update with invalid email', function () {
    createAuthenticatedUser();

    $response = $this->patchJson('/api/v1/account/profile', [
        'name' => 'Name',
        'email' => 'not-an-email',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

it('rejects profile update when email is taken by another user', function () {
    User::factory()->create(['email' => 'taken@example.com']);
    createAuthenticatedUser(['email' => 'mine@example.com']);

    $response = $this->patchJson('/api/v1/account/profile', [
        'name' => 'Name',
        'email' => 'taken@example.com',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

it('allows keeping the same email on profile update', function () {
    $user = createAuthenticatedUser(['email' => 'same@example.com']);

    $response = $this->patchJson('/api/v1/account/profile', [
        'name' => 'Same Email',
        'email' => 'same@example.com',
    ]);

    $response->assertOk()
        ->assertJsonPath('email', 'same@example.com');
});

it('rejects profile update when unauthenticated', function () {
    $this->patchJson('/api/v1/account/profile', [
        'name' => 'Name',
        'email' => 'x@example.com',
    ])->assertStatus(401);
});

it('deletes the user account with correct password', function () {
    $user = createAuthenticatedUser([
        'email' => 'delete@example.com',
        'password' => Hash::make('current-pass'),
    ]);

    $response = $this->deleteJson('/api/v1/account/profile', [
        'password' => 'current-pass',
    ]);

    $response->assertNoContent();

    expect(User::find($user->id))->toBeNull();
});

it('rejects account deletion with wrong password', function () {
    createAuthenticatedUser([
        'password' => Hash::make('current-pass'),
    ]);

    $response = $this->deleteJson('/api/v1/account/profile', [
        'password' => 'wrong-pass',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['password']);
});

it('rejects account deletion when password is missing', function () {
    createAuthenticatedUser();

    $response = $this->deleteJson('/api/v1/account/profile', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['password']);
});

it('rejects account deletion when unauthenticated', function () {
    $this->deleteJson('/api/v1/account/profile', [
        'password' => 'anything',
    ])->assertStatus(401);
});
