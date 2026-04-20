<?php

use App\Models\App;
use App\Models\User;

test('dashboard returns summary for authenticated user', function () {
    $user = User::factory()->create();
    $app = App::factory()->create();
    $app->users()->attach($user->id, []);

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/dashboard');

    $response->assertOk()
        ->assertJsonStructure([
            'total_apps',
            'total_versions',
            'total_changes',
            'recent_changes',
        ]);

    expect($response->json('total_apps'))->toBe(1);
});

test('dashboard returns 401 without auth', function () {
    $response = $this->getJson('/api/v1/dashboard');

    $response->assertStatus(401);
});

test('dashboard only shows data for owned apps', function () {
    $user = User::factory()->create();
    $ownedApp = App::factory()->create();
    $ownedApp->users()->attach($user->id, []);

    $otherApp = App::factory()->create();

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/dashboard');

    $response->assertOk();
    expect($response->json('total_apps'))->toBe(1);
});
