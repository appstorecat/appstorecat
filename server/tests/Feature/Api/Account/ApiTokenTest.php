<?php

declare(strict_types=1);
use App\Models\User;

it('lists user API tokens excluding auth-token', function () {
    $user = createAuthenticatedUser();

    // Internal session token — must be excluded from the list.
    $user->createToken('auth-token');

    $user->createToken('MCP Token A', ['mcp']);
    $user->createToken('MCP Token B', ['mcp']);

    $response = $this->getJson('/api/v1/account/api-tokens');

    $response->assertOk()
        ->assertJsonCount(2)
        ->assertJsonStructure([
            '*' => ['id', 'name', 'abilities', 'last_used_at', 'created_at'],
        ]);

    $names = collect($response->json())->pluck('name')->all();
    expect($names)->not->toContain('auth-token')
        ->and($names)->toContain('MCP Token A', 'MCP Token B');
});

it('returns an empty list when the user has no API tokens', function () {
    createAuthenticatedUser();

    $response = $this->getJson('/api/v1/account/api-tokens');

    $response->assertOk()->assertJsonCount(0);
});

it('rejects token index when unauthenticated', function () {
    $this->getJson('/api/v1/account/api-tokens')->assertStatus(401);
});

it('creates a new API token and returns plaintext once', function () {
    $user = createAuthenticatedUser();

    $response = $this->postJson('/api/v1/account/api-tokens', [
        'name' => 'My MCP Token',
    ]);

    $response->assertCreated()
        ->assertJsonStructure([
            'token' => ['id', 'name', 'abilities', 'created_at'],
            'plain_text_token',
        ])
        ->assertJsonPath('token.name', 'My MCP Token');

    expect($response->json('plain_text_token'))->toBeString()->not->toBeEmpty()
        ->and($response->json('token.abilities'))->toContain('mcp');

    expect($user->tokens()->where('name', 'My MCP Token')->exists())->toBeTrue();
});

it('rejects token creation when name is missing', function () {
    createAuthenticatedUser();

    $response = $this->postJson('/api/v1/account/api-tokens', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['name']);
});

it('rejects token creation when name exceeds max length', function () {
    createAuthenticatedUser();

    $response = $this->postJson('/api/v1/account/api-tokens', [
        'name' => str_repeat('a', 256),
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['name']);
});

it('rejects token creation when unauthenticated', function () {
    $this->postJson('/api/v1/account/api-tokens', [
        'name' => 'Token',
    ])->assertStatus(401);
});

it('revokes an API token by id', function () {
    $user = createAuthenticatedUser();
    $token = $user->createToken('Revoke Me', ['mcp']);
    $tokenId = $token->accessToken->id;

    $response = $this->deleteJson("/api/v1/account/api-tokens/{$tokenId}");

    $response->assertNoContent();

    expect($user->tokens()->where('id', $tokenId)->exists())->toBeFalse();
});

it('returns 404 when revoking a non-existent token', function () {
    createAuthenticatedUser();

    $response = $this->deleteJson('/api/v1/account/api-tokens/99999');

    $response->assertNotFound();
});

it('refuses to revoke the auth-token via the api-tokens endpoint', function () {
    $user = createAuthenticatedUser();
    $authToken = $user->createToken('auth-token');
    $tokenId = $authToken->accessToken->id;

    $response = $this->deleteJson("/api/v1/account/api-tokens/{$tokenId}");

    $response->assertNotFound();

    expect($user->tokens()->where('id', $tokenId)->exists())->toBeTrue();
});

it('cannot revoke another user\'s token', function () {
    $owner = createAuthenticatedUser();
    $otherUser = User::factory()->create();
    $otherToken = $otherUser->createToken('Other Token', ['mcp']);

    $response = $this->deleteJson("/api/v1/account/api-tokens/{$otherToken->accessToken->id}");

    $response->assertNotFound();

    expect($otherUser->tokens()->where('id', $otherToken->accessToken->id)->exists())->toBeTrue();
});

it('rejects token revoke when unauthenticated', function () {
    $this->deleteJson('/api/v1/account/api-tokens/1')->assertStatus(401);
});
