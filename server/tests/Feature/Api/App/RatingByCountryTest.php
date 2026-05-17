<?php

declare(strict_types=1);

use App\Enums\Platform;
use App\Models\App;
use App\Models\AppMetric;

beforeEach(function () {
    createAuthenticatedUser();
});

it('returns the latest AppMetric per country for an iOS app', function () {
    $app = App::factory()->create(['platform' => Platform::Ios]);

    // Older + newer snapshot for "us" — newest should win.
    AppMetric::factory()->create([
        'app_id' => $app->id, 'country_code' => 'us', 'date' => '2026-04-15',
        'rating' => 3.0, 'rating_count' => 50,
    ]);
    AppMetric::factory()->create([
        'app_id' => $app->id, 'country_code' => 'us', 'date' => '2026-05-15',
        'rating' => 4.5, 'rating_count' => 200,
    ]);
    AppMetric::factory()->create([
        'app_id' => $app->id, 'country_code' => 'tr', 'date' => '2026-05-15',
        'rating' => 4.0, 'rating_count' => 120,
    ]);

    $response = $this->getJson("/api/v1/apps/ios/{$app->external_id}/ratings/country-breakdown");

    $response->assertOk()->assertJsonCount(2, 'data');

    $byCountry = collect($response->json('data'))->keyBy('country_code');

    expect($byCountry)->toHaveKeys(['us', 'tr'])
        ->and($byCountry['us']['rating'])->toBe(4.5)
        ->and($byCountry['us']['rating_count'])->toBe(200)
        ->and((float) $byCountry['tr']['rating'])->toBe(4.0)
        ->and($byCountry['tr']['rating_count'])->toBe(120);
});

it('includes the GLOBAL_COUNTRY (zz) row if present — it is just one country among others on iOS', function () {
    $app = App::factory()->create(['platform' => Platform::Ios]);

    AppMetric::factory()->create([
        'app_id' => $app->id, 'country_code' => 'us', 'date' => '2026-05-15',
        'rating' => 4.5, 'rating_count' => 200,
    ]);
    AppMetric::factory()->create([
        'app_id' => $app->id, 'country_code' => 'zz', 'date' => '2026-05-15',
        'rating' => 4.0, 'rating_count' => 1000,
    ]);

    $response = $this->getJson("/api/v1/apps/ios/{$app->external_id}/ratings/country-breakdown");

    $response->assertOk();
    $codes = collect($response->json('data'))->pluck('country_code')->all();
    expect($codes)->toContain('us', 'zz');
});

it('flags the country-breakdown endpoint as unsupported on Android', function () {
    $app = App::factory()->android()->create();

    AppMetric::factory()->create([
        'app_id' => $app->id, 'country_code' => 'zz', 'date' => '2026-05-15',
        'rating' => 4.5, 'rating_count' => 1500,
    ]);

    $response = $this->getJson("/api/v1/apps/android/{$app->external_id}/ratings/country-breakdown");

    $response->assertOk()
        ->assertJsonCount(0, 'data')
        ->assertJsonPath('supported', false);
});

it('returns an empty list when an iOS app has no metric rows', function () {
    $app = App::factory()->create(['platform' => Platform::Ios]);

    $response = $this->getJson("/api/v1/apps/ios/{$app->external_id}/ratings/country-breakdown");

    $response->assertOk()
        ->assertJsonCount(0, 'data')
        ->assertJsonPath('supported', true);
});

it('returns 404 from the country breakdown endpoint when the app is unknown', function () {
    $this->getJson('/api/v1/apps/ios/missing/ratings/country-breakdown')
        ->assertStatus(404);
});
