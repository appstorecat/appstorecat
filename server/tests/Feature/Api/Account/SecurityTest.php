<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Hash;

it('updates password with valid current password', function () {
    $user = createAuthenticatedUser([
        'password' => Hash::make('old-secret-pass'),
    ]);

    $response = $this->putJson('/api/v1/account/password', [
        'current_password' => 'old-secret-pass',
        'password' => 'New-Secret-Pass!1',
        'password_confirmation' => 'New-Secret-Pass!1',
    ]);

    $response->assertOk();

    $user->refresh();
    expect(Hash::check('New-Secret-Pass!1', $user->password))->toBeTrue()
        ->and(Hash::check('old-secret-pass', $user->password))->toBeFalse();
});

it('rejects password update when current password is wrong', function () {
    createAuthenticatedUser([
        'password' => Hash::make('old-secret-pass'),
    ]);

    $response = $this->putJson('/api/v1/account/password', [
        'current_password' => 'wrong-pass',
        'password' => 'New-Secret-Pass!1',
        'password_confirmation' => 'New-Secret-Pass!1',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['current_password']);
});

it('rejects password update when confirmation does not match', function () {
    createAuthenticatedUser([
        'password' => Hash::make('old-secret-pass'),
    ]);

    $response = $this->putJson('/api/v1/account/password', [
        'current_password' => 'old-secret-pass',
        'password' => 'New-Secret-Pass!1',
        'password_confirmation' => 'different',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['password']);
});

it('rejects password update when required fields are missing', function () {
    createAuthenticatedUser();

    $response = $this->putJson('/api/v1/account/password', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['current_password', 'password']);
});

it('rejects password update when unauthenticated', function () {
    $this->putJson('/api/v1/account/password', [
        'current_password' => 'anything',
        'password' => 'New-Secret-Pass!1',
        'password_confirmation' => 'New-Secret-Pass!1',
    ])->assertStatus(401);
});
