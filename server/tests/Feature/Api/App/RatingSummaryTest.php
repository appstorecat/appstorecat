<?php

declare(strict_types=1);

use App\Enums\Platform;
use App\Models\App;
use App\Models\AppMetric;

beforeEach(function () {
    createAuthenticatedUser();
});

it('returns the latest aggregated rating snapshot and a 30-day baseline trend', function () {
    $app = App::factory()->create(['platform' => Platform::Ios, 'external_id' => '900']);
    $latest = '2026-05-15';
    $baseline = '2026-04-15';

    AppMetric::factory()->create([
        'app_id' => $app->id,
        'country_code' => 'us',
        'date' => $baseline,
        'rating' => 4.0,
        'rating_count' => 100,
        'rating_breakdown' => ['1' => 10, '2' => 5, '3' => 5, '4' => 30, '5' => 50],
    ]);
    AppMetric::factory()->create([
        'app_id' => $app->id,
        'country_code' => 'us',
        'date' => $latest,
        'rating' => 4.5,
        'rating_count' => 250,
        'rating_breakdown' => ['1' => 10, '2' => 5, '3' => 10, '4' => 75, '5' => 150],
    ]);

    $response = $this->getJson("/api/v1/apps/ios/{$app->external_id}/ratings/summary");

    $response->assertOk()
        ->assertJsonPath('rating', 4.5)
        ->assertJsonPath('rating_count', 250)
        ->assertJsonPath('trend.rating_delta_30d', 0.5)
        ->assertJsonPath('trend.rating_count_delta_30d', 150)
        ->assertJsonPath('breakdown.5', 150);
});

it('returns null trend deltas when no baseline snapshot is available within 30 days', function () {
    $app = App::factory()->create(['platform' => Platform::Ios]);

    AppMetric::factory()->create([
        'app_id' => $app->id,
        'country_code' => 'us',
        'date' => '2026-05-15',
        'rating' => 4.2,
        'rating_count' => 80,
        'rating_breakdown' => ['1' => 1, '2' => 1, '3' => 4, '4' => 30, '5' => 44],
    ]);

    $response = $this->getJson("/api/v1/apps/ios/{$app->external_id}/ratings/summary");

    $response->assertOk()
        ->assertJsonPath('rating', 4.2)
        ->assertJsonPath('rating_count', 80)
        ->assertJsonPath('trend.rating_delta_30d', null)
        ->assertJsonPath('trend.rating_count_delta_30d', null);
});

it('returns zeroed numeric fields with no breakdown when the app has no metrics at all', function () {
    $app = App::factory()->create(['platform' => Platform::Ios]);

    $response = $this->getJson("/api/v1/apps/ios/{$app->external_id}/ratings/summary");

    $response->assertOk()
        ->assertJsonPath('rating', 0)
        ->assertJsonPath('rating_count', 0)
        ->assertJsonPath('breakdown', null)
        ->assertJsonPath('trend.rating_delta_30d', null)
        ->assertJsonPath('trend.rating_count_delta_30d', null);
});

it('aggregates iOS multi-country rows into a global summary using rating-count weighting', function () {
    $app = App::factory()->create(['platform' => Platform::Ios]);

    AppMetric::factory()->create([
        'app_id' => $app->id, 'country_code' => 'us', 'date' => '2026-05-15',
        'rating' => 5.0, 'rating_count' => 100,
        'rating_breakdown' => ['1' => 0, '2' => 0, '3' => 0, '4' => 0, '5' => 100],
    ]);
    AppMetric::factory()->create([
        'app_id' => $app->id, 'country_code' => 'tr', 'date' => '2026-05-15',
        'rating' => 3.0, 'rating_count' => 100,
        'rating_breakdown' => ['1' => 50, '2' => 0, '3' => 0, '4' => 0, '5' => 50],
    ]);

    $response = $this->getJson("/api/v1/apps/ios/{$app->external_id}/ratings/summary");

    $response->assertOk()
        ->assertJsonPath('rating_count', 200)
        ->assertJsonPath('breakdown.5', 150)
        ->assertJsonPath('breakdown.1', 50);

    expect((float) $response->json('rating'))->toBe(4.0);
});

it('reads the global (zz) row for Android ratings summary', function () {
    $app = App::factory()->android()->create();

    AppMetric::factory()->create([
        'app_id' => $app->id, 'country_code' => 'zz', 'date' => '2026-04-15',
        'rating' => 4.0, 'rating_count' => 1000,
        'rating_breakdown' => ['1' => 100, '2' => 100, '3' => 100, '4' => 300, '5' => 400],
    ]);
    AppMetric::factory()->create([
        'app_id' => $app->id, 'country_code' => 'zz', 'date' => '2026-05-15',
        'rating' => 4.5, 'rating_count' => 1500,
        'rating_breakdown' => ['1' => 100, '2' => 100, '3' => 100, '4' => 400, '5' => 800],
    ]);

    $response = $this->getJson("/api/v1/apps/android/{$app->external_id}/ratings/summary");

    $response->assertOk()
        ->assertJsonPath('rating', 4.5)
        ->assertJsonPath('rating_count', 1500)
        ->assertJsonPath('trend.rating_delta_30d', 0.5)
        ->assertJsonPath('trend.rating_count_delta_30d', 500);
});

it('returns 404 from the rating summary endpoint when the app is unknown', function () {
    $this->getJson('/api/v1/apps/ios/nope/ratings/summary')
        ->assertStatus(404);
});
