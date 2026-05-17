<?php

declare(strict_types=1);

use App\Enums\Platform;
use App\Models\App;
use App\Models\Publisher;
use Illuminate\Support\Facades\Http;

it('returns the store apps payload and discovers each app in the catalog', function () {
    $user = createAuthenticatedUser();

    $publisher = Publisher::factory()->create([
        'platform' => Platform::Ios,
        'external_id' => '389801255',
        'name' => 'Meta Platforms, Inc.',
    ]);

    Http::fake([
        '*/developers/389801255/apps*' => Http::response([
            'apps' => [
                [
                    'external_id' => 'new-app-1',
                    'name' => 'Brand New App',
                    'icon_url' => 'https://example.com/new.png',
                    'rating' => 4.5,
                    'rating_count' => 1200,
                    'is_free' => true,
                    'category' => 'Social Networking',
                ],
                [
                    'external_id' => 'new-app-2',
                    'name' => 'Another App',
                    'is_free' => false,
                    'category' => 'Photo & Video',
                ],
            ],
        ], 200),
    ]);

    expect(App::where('external_id', 'new-app-1')->exists())->toBeFalse()
        ->and(App::where('external_id', 'new-app-2')->exists())->toBeFalse();

    $response = $this->getJson('/api/v1/publishers/ios/389801255/store-apps');

    $response->assertOk()
        ->assertJsonCount(2, 'apps')
        ->assertJsonPath('apps.0.external_id', 'new-app-1')
        ->assertJsonPath('apps.0.name', 'Brand New App')
        ->assertJsonPath('apps.1.external_id', 'new-app-2');

    expect(
        App::platform(Platform::Ios)->where('external_id', 'new-app-1')->exists()
    )->toBeTrue();
    expect(
        App::platform(Platform::Ios)->where('external_id', 'new-app-2')->exists()
    )->toBeTrue();

    $discovered = App::where('external_id', 'new-app-1')->first();
    expect($discovered->publisher_id)->toBe($publisher->id);
});

it('marks already-tracked apps with is_tracked=true', function () {
    $user = createAuthenticatedUser();

    $publisher = Publisher::factory()->create([
        'platform' => Platform::Ios,
        'external_id' => '12345',
    ]);

    $existing = App::factory()->for($publisher)->create([
        'platform' => Platform::Ios,
        'external_id' => 'already-tracked',
    ]);
    $user->apps()->attach($existing->id);

    Http::fake([
        '*/developers/12345/apps*' => Http::response([
            'apps' => [
                ['external_id' => 'already-tracked', 'name' => 'Tracked', 'is_free' => true],
                ['external_id' => 'fresh', 'name' => 'Fresh', 'is_free' => true],
            ],
        ], 200),
    ]);

    $response = $this->getJson('/api/v1/publishers/ios/12345/store-apps');

    $response->assertOk()
        ->assertJsonCount(2, 'apps')
        ->assertJsonPath('apps.0.is_tracked', true)
        ->assertJsonPath('apps.1.is_tracked', false);
});

it('returns 404 when the publisher is not in the DB', function () {
    createAuthenticatedUser();

    // Even with a successful scraper response, the controller short-circuits
    // before any HTTP call when the publisher row does not exist.
    $this->getJson('/api/v1/publishers/ios/unknown/store-apps')
        ->assertStatus(404);
});

it('returns an empty apps list when the scraper call fails', function () {
    createAuthenticatedUser();

    Publisher::factory()->create([
        'platform' => Platform::Ios,
        'external_id' => '99999',
    ]);

    Http::fake([
        '*/developers/99999/apps*' => Http::response(['error' => 'boom'], 500),
    ]);

    $response = $this->getJson('/api/v1/publishers/ios/99999/store-apps');

    $response->assertOk()->assertJsonCount(0, 'apps');
});

it('hits the Google Play scraper for android publishers', function () {
    createAuthenticatedUser();

    Publisher::factory()->android()->create([
        'external_id' => 'Example+Studio',
        'name' => 'Example Studio',
    ]);

    Http::fake([
        '*/developers/Example+Studio/apps*' => Http::response([
            'apps' => [
                [
                    'external_id' => 'com.example.android',
                    'name' => 'Android App',
                    'is_free' => true,
                ],
            ],
        ], 200),
    ]);

    $response = $this->getJson('/api/v1/publishers/android/Example+Studio/store-apps');

    $response->assertOk()
        ->assertJsonCount(1, 'apps')
        ->assertJsonPath('apps.0.external_id', 'com.example.android');

    expect(
        App::platform(Platform::Android)->where('external_id', 'com.example.android')->exists()
    )->toBeTrue();
});

it('rejects unauthenticated store-apps requests', function () {
    Publisher::factory()->create([
        'platform' => Platform::Ios,
        'external_id' => '42',
    ]);

    $this->getJson('/api/v1/publishers/ios/42/store-apps')->assertStatus(401);
});
