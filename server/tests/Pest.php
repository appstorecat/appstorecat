<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Unit');

/**
 * Create a fresh User and authenticate them via Sanctum.
 * Pass attributes to override factory defaults.
 */
function createAuthenticatedUser(array $attributes = []): User
{
    $user = User::factory()->create($attributes);
    Sanctum::actingAs($user, ['*']);

    return $user;
}
