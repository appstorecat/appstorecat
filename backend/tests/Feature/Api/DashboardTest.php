<?php

use App\Models\App;
use App\Models\Review;
use App\Models\User;

test('dashboard returns summary for authenticated user', function () {
    $user = User::factory()->create();
    $app = App::factory()->create();
    $app->users()->attach($user->id, []);

    Review::factory()->count(3)->create(['app_id' => $app->id]);

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/dashboard');

    $response->assertOk()
        ->assertJsonStructure([
            'total_apps',
            'total_reviews',
            'total_versions',
            'total_changes',
            'recent_reviews',
            'recent_changes',
        ]);

    expect($response->json('total_apps'))->toBe(1);
    expect($response->json('total_reviews'))->toBe(3);
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
    Review::factory()->count(5)->create(['app_id' => $otherApp->id]);

    Review::factory()->count(2)->create(['app_id' => $ownedApp->id]);

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/dashboard');

    $response->assertOk();
    expect($response->json('total_apps'))->toBe(1);
    expect($response->json('total_reviews'))->toBe(2);
});
