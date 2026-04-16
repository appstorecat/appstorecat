<?php

use App\Models\App;
use App\Models\Review;
use App\Models\User;

function setupReviewTest(): array
{
    $user = User::factory()->create();
    $app = App::factory()->create();

    return [$user, $app];
}

test('review index returns paginated reviews', function () {
    [$user, $app] = setupReviewTest();
    Review::factory()->count(3)->create(['app_id' => $app->id]);

    $response = $this->actingAs($user, 'sanctum')
        ->getJson("/api/v1/apps/{$app->platform->value}/{$app->external_id}/reviews");

    $response->assertOk()
        ->assertJsonStructure(['data', 'links', 'meta']);

    expect($response->json('data'))->toHaveCount(3);
});

test('review index filters by rating', function () {
    [$user, $app] = setupReviewTest();
    Review::factory()->create(['app_id' => $app->id, 'rating' => 5]);
    Review::factory()->create(['app_id' => $app->id, 'rating' => 1]);

    $response = $this->actingAs($user, 'sanctum')
        ->getJson("/api/v1/apps/{$app->platform->value}/{$app->external_id}/reviews?rating=5");

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.rating'))->toBe(5);
});

test('review index filters by country', function () {
    [$user, $app] = setupReviewTest();
    Review::factory()->create(['app_id' => $app->id, 'country_code' => 'US']);
    Review::factory()->create(['app_id' => $app->id, 'country_code' => 'TR']);

    $response = $this->actingAs($user, 'sanctum')
        ->getJson("/api/v1/apps/{$app->platform->value}/{$app->external_id}/reviews?country_code=TR");

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.country_code'))->toBe('TR');
});

test('review summary returns distribution', function () {
    [$user, $app] = setupReviewTest();
    Review::factory()->create(['app_id' => $app->id, 'rating' => 5, 'country_code' => 'US']);
    Review::factory()->create(['app_id' => $app->id, 'rating' => 5, 'country_code' => 'US']);
    Review::factory()->create(['app_id' => $app->id, 'rating' => 1, 'country_code' => 'TR']);

    $response = $this->actingAs($user, 'sanctum')
        ->getJson("/api/v1/apps/{$app->platform->value}/{$app->external_id}/reviews/summary");

    $response->assertOk()
        ->assertJsonStructure(['total_reviews', 'average_rating', 'distribution', 'countries']);

    $json = $response->json();
    expect($json['total_reviews'])->toBe(3);
    expect($json['distribution']['5'])->toBe(2);
    expect($json['distribution']['1'])->toBe(1);
    expect($json['countries'])->toContain('US', 'TR');
});

test('review index returns 404 for nonexistent app', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/apps/ios/nonexistent.app.id/reviews');

    $response->assertNotFound();
});

test('review index returns 401 without auth', function () {
    $app = App::factory()->create();

    $response = $this->getJson("/api/v1/apps/{$app->platform->value}/{$app->external_id}/reviews");

    $response->assertStatus(401);
});
