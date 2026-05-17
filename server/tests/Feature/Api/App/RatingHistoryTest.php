<?php

declare(strict_types=1);

use App\Models\App;
use App\Models\AppMetric;
use Carbon\Carbon;

beforeEach(function () {
    createAuthenticatedUser();
    // Freeze "now" so the rolling window is deterministic.
    Carbon::setTestNow(Carbon::parse('2026-05-15 12:00:00'));
});

afterEach(function () {
    Carbon::setTestNow();
});

it('returns one history point per day in the requested window, sorted ascending', function () {
    $app = App::factory()->android()->create();

    // Five days of snapshots ending today.
    foreach (['2026-05-11', '2026-05-12', '2026-05-13', '2026-05-14', '2026-05-15'] as $date) {
        AppMetric::factory()->create([
            'app_id' => $app->id,
            'country_code' => 'zz',
            'date' => $date,
            'rating' => 4.0,
            'rating_count' => 100,
            'rating_breakdown' => ['1' => 0, '2' => 0, '3' => 0, '4' => 50, '5' => 50],
        ]);
    }

    $response = $this->getJson("/api/v1/apps/android/{$app->external_id}/ratings/history?days=5");

    $response->assertOk()->assertJsonCount(5);

    $dates = collect($response->json())->pluck('date')->all();
    expect($dates)->toBe(['2026-05-11', '2026-05-12', '2026-05-13', '2026-05-14', '2026-05-15']);
});

it('emits null placeholder points for days that have no snapshot', function () {
    $app = App::factory()->android()->create();

    AppMetric::factory()->create([
        'app_id' => $app->id, 'country_code' => 'zz', 'date' => '2026-05-15',
        'rating' => 4.5, 'rating_count' => 200,
        'rating_breakdown' => ['1' => 0, '2' => 0, '3' => 0, '4' => 100, '5' => 100],
    ]);

    $response = $this->getJson("/api/v1/apps/android/{$app->external_id}/ratings/history?days=3");

    $response->assertOk()->assertJsonCount(3);

    // The first two days have no snapshot — placeholders should be null.
    expect($response->json('0.rating'))->toBeNull()
        ->and($response->json('0.rating_count'))->toBeNull()
        ->and($response->json('0.delta_total'))->toBeNull()
        ->and($response->json('2.rating'))->toBe(4.5)
        ->and($response->json('2.rating_count'))->toBe(200);
});

it('computes per-star delta_breakdown and delta_total against the prior snapshot', function () {
    $app = App::factory()->android()->create();

    // Baseline before the window — used to compute delta on the first day.
    AppMetric::factory()->create([
        'app_id' => $app->id, 'country_code' => 'zz', 'date' => '2026-05-10',
        'rating' => 4.0, 'rating_count' => 100,
        'rating_breakdown' => ['1' => 10, '2' => 10, '3' => 10, '4' => 30, '5' => 40],
    ]);

    AppMetric::factory()->create([
        'app_id' => $app->id, 'country_code' => 'zz', 'date' => '2026-05-15',
        'rating' => 4.2, 'rating_count' => 200,
        'rating_breakdown' => ['1' => 10, '2' => 10, '3' => 10, '4' => 60, '5' => 110],
    ]);

    $response = $this->getJson("/api/v1/apps/android/{$app->external_id}/ratings/history?days=1");

    $response->assertOk()
        ->assertJsonPath('0.delta_total', 100)
        ->assertJsonPath('0.delta_breakdown.5', 70)
        ->assertJsonPath('0.delta_breakdown.4', 30);
});

it('respects the days parameter for the window size', function () {
    $app = App::factory()->android()->create();

    $response = $this->getJson("/api/v1/apps/android/{$app->external_id}/ratings/history?days=7");

    $response->assertOk()->assertJsonCount(7);
});

it('defaults to a 30-day window when days is not provided', function () {
    $app = App::factory()->android()->create();

    $response = $this->getJson("/api/v1/apps/android/{$app->external_id}/ratings/history");

    $response->assertOk()->assertJsonCount(30);
});

it('rejects history requests with days outside the allowed 1..90 range', function () {
    $app = App::factory()->android()->create();

    $this->getJson("/api/v1/apps/android/{$app->external_id}/ratings/history?days=0")->assertStatus(422);
    $this->getJson("/api/v1/apps/android/{$app->external_id}/ratings/history?days=200")->assertStatus(422);
});

it('returns 404 from the history endpoint for unknown apps', function () {
    $this->getJson('/api/v1/apps/ios/nope/ratings/history')
        ->assertStatus(404);
});
