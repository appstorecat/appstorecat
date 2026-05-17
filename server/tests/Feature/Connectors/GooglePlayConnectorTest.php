<?php

declare(strict_types=1);

use App\Connectors\GooglePlayConnector;
use App\Models\App;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('appstorecat.connectors.gplay.base_url', 'http://scraper-android:7463');
    config()->set('appstorecat.connectors.gplay.timeout', 30);
});

/*
|--------------------------------------------------------------------------
| fetchIdentity
|--------------------------------------------------------------------------
*/

it('fetchIdentity maps a successful identity response into ConnectorResult::success', function () {
    $app = App::factory()->android()->create([
        'external_id' => 'com.example.app',
    ]);

    Http::fake([
        'http://scraper-android:7463/apps/com.example.app/identity*' => Http::response([
            'app_id' => 'com.example.app',
            'name' => 'Example Android',
            'publisher_name' => 'Example LLC',
            'publisher_external_id' => 'Example+LLC',
            'publisher_url' => 'https://example.com',
            'category' => 'PRODUCTIVITY',
            'category_id' => 'PRODUCTIVITY',
            'content_rating' => 'Everyone',
            'supported_locales' => ['en', 'tr'],
            'original_release_date' => '2019-06-10',
            'price_model' => 'free',
            'version' => '2.0.0',
            'current_version_release_date' => '2026-03-15',
        ], 200),
    ]);

    $result = (new GooglePlayConnector)->fetchIdentity($app, 'us');

    expect($result->success)->toBeTrue()
        ->and($result->data['external_id'])->toBe('com.example.app')
        ->and($result->data['name'])->toBe('Example Android')
        ->and($result->data['publisher_name'])->toBe('Example LLC')
        ->and($result->data['publisher_external_id'])->toBe('Example+LLC')
        ->and($result->data['category_primary'])->toBe('PRODUCTIVITY')
        ->and($result->data['supported_locales'])->toBe(['en', 'tr'])
        ->and($result->data['original_release_date'])->toBe('2019-06-10')
        ->and($result->data['is_free'])->toBeTrue()
        ->and($result->data['version'])->toBe('2.0.0')
        ->and($result->data['current_version_release_date'])->toBe('2026-03-15');

    Http::assertSent(fn ($request) => str_contains($request->url(), '/apps/com.example.app/identity')
        && str_contains($request->url(), 'country=us'));
});

it('fetchIdentity converts "Never updated" current_version_release_date to null (regression 0a021d8)', function () {
    $app = App::factory()->android()->create(['external_id' => 'com.legacy.app']);

    Http::fake([
        'http://scraper-android:7463/apps/com.legacy.app/identity*' => Http::response([
            'app_id' => 'com.legacy.app',
            'name' => 'Legacy App',
            'price_model' => 'free',
            'original_release_date' => '2015-01-01',
            'current_version_release_date' => 'Never updated',
        ], 200),
    ]);

    $result = (new GooglePlayConnector)->fetchIdentity($app, 'us');

    expect($result->success)->toBeTrue()
        ->and($result->data['original_release_date'])->toBe('2015-01-01')
        ->and($result->data['current_version_release_date'])->toBeNull();
});

it('fetchIdentity returns failure on 404', function () {
    $app = App::factory()->android()->create(['external_id' => 'com.missing.app']);

    Http::fake([
        'http://scraper-android:7463/apps/com.missing.app/identity*' => Http::response(['detail' => 'not found'], 404),
    ]);

    $result = (new GooglePlayConnector)->fetchIdentity($app, 'us');

    expect($result->success)->toBeFalse()
        ->and($result->error)->toContain('Google Play fetch failed');
});

it('fetchIdentity returns failure on HTTP 500', function () {
    $app = App::factory()->android()->create(['external_id' => 'com.boom.app']);

    Http::fake([
        'http://scraper-android:7463/apps/com.boom.app/identity*' => Http::response(['detail' => 'boom'], 500),
    ]);

    $result = (new GooglePlayConnector)->fetchIdentity($app, 'us');

    expect($result->success)->toBeFalse()
        ->and($result->error)->toContain('Google Play fetch failed')
        ->and($result->error)->toContain('500');
});

it('fetchIdentity returns failure on empty body', function () {
    $app = App::factory()->android()->create(['external_id' => 'com.empty.app']);

    Http::fake([
        'http://scraper-android:7463/apps/com.empty.app/identity*' => Http::response(null, 500),
    ]);

    $result = (new GooglePlayConnector)->fetchIdentity($app, 'us');

    expect($result->success)->toBeFalse()
        ->and($result->error)->toContain('Google Play fetch failed');
});

/*
|--------------------------------------------------------------------------
| fetchListings
|--------------------------------------------------------------------------
*/

it('fetchListings passes subtitle through unmodified even when 256+ chars (no truncation, audit commit f5f7af6)', function () {
    $app = App::factory()->android()->create(['external_id' => 'com.long.subtitle']);

    $longSubtitle = str_repeat('A', 300);

    Http::fake([
        'http://scraper-android:7463/apps/com.long.subtitle/listings*' => Http::response([
            'title' => 'Long Title',
            'subtitle' => $longSubtitle,
            'description' => 'Body',
            'screenshots' => [],
        ], 200),
    ]);

    $result = (new GooglePlayConnector)->fetchListings($app, 'us', 'en');

    expect($result->success)->toBeTrue()
        ->and($result->data['subtitle'])->toBe($longSubtitle)
        ->and(strlen($result->data['subtitle']))->toBe(300);
});

it('fetchListings routes locale to /listings?locale=&country= and maps screenshots', function () {
    $app = App::factory()->android()->create(['external_id' => 'com.with.locale']);

    Http::fake([
        'http://scraper-android:7463/apps/com.with.locale/listings*' => Http::response([
            'title' => 'Title',
            'description' => 'Desc',
            'icon_url' => 'https://cdn/icon.png',
            'screenshots' => [
                ['url' => 'https://cdn/g1.png', 'device_type' => 'phone', 'order' => 0],
                ['url' => 'https://cdn/g2.png', 'device_type' => 'tablet', 'order' => 1],
            ],
        ], 200),
    ]);

    $result = (new GooglePlayConnector)->fetchListings($app, 'tr', 'tr-TR');

    expect($result->success)->toBeTrue()
        ->and($result->data['platform'])->toBe('android')
        ->and($result->data['locale'])->toBe('tr-TR')
        ->and($result->data['screenshots'])->toHaveCount(2)
        ->and($result->data['screenshots'][0]['device_type'])->toBe('phone');

    Http::assertSent(fn ($request) => str_contains($request->url(), '/apps/com.with.locale/listings')
        && str_contains($request->url(), 'locale=tr-TR')
        && str_contains($request->url(), 'country=tr'));
});

it('fetchListings falls back to country as locale when locale is null', function () {
    $app = App::factory()->android()->create(['external_id' => 'com.no.locale']);

    Http::fake([
        'http://scraper-android:7463/apps/com.no.locale/listings*' => Http::response([
            'title' => 'X',
            'screenshots' => [],
        ], 200),
    ]);

    $result = (new GooglePlayConnector)->fetchListings($app, 'us');

    expect($result->success)->toBeTrue();

    Http::assertSent(fn ($request) => str_contains($request->url(), 'locale=us')
        && str_contains($request->url(), 'country=us'));
});

/*
|--------------------------------------------------------------------------
| fetchMetrics
|--------------------------------------------------------------------------
*/

it('fetchMetrics maps rating, rating_count, rating_breakdown and installs_range string', function () {
    $app = App::factory()->android()->create(['external_id' => 'com.metrics.app']);

    Http::fake([
        'http://scraper-android:7463/apps/com.metrics.app/metrics*' => Http::response([
            'rating' => 4.2,
            'rating_count' => 50000,
            'rating_breakdown' => ['1' => 1000, '5' => 30000],
            'installs_range' => '1,000,000+',
            'file_size_bytes' => 9876543,
        ], 200),
    ]);

    $result = (new GooglePlayConnector)->fetchMetrics($app, 'us');

    expect($result->success)->toBeTrue()
        ->and($result->data['rating'])->toBe(4.2)
        ->and($result->data['rating_count'])->toBe(50000)
        ->and($result->data['rating_breakdown'])->toBe(['1' => 1000, '5' => 30000])
        ->and($result->data['installs_range'])->toBe('1,000,000+')
        ->and($result->data['file_size_bytes'])->toBe(9876543);
});

/*
|--------------------------------------------------------------------------
| fetchDeveloperApps
|--------------------------------------------------------------------------
*/

it('fetchDeveloperApps returns the mapped apps array', function () {
    Http::fake([
        'http://scraper-android:7463/developers/dev-42/apps*' => Http::response([
            'apps' => [
                [
                    'external_id' => 'com.dev.one',
                    'name' => 'Dev One',
                    'icon_url' => 'https://cdn/one.png',
                    'rating' => 3.9,
                    'rating_count' => 500,
                    'price_model' => 'free',
                    'category' => 'GAME',
                ],
                [
                    'external_id' => 'com.dev.two',
                    'name' => 'Dev Two',
                    'price_model' => 'paid',
                ],
            ],
        ], 200),
    ]);

    $result = (new GooglePlayConnector)->fetchDeveloperApps('dev-42');

    expect($result->success)->toBeTrue()
        ->and($result->data['apps'])->toHaveCount(2)
        ->and($result->data['apps'][0]['external_id'])->toBe('com.dev.one')
        ->and($result->data['apps'][0]['is_free'])->toBeTrue()
        ->and($result->data['apps'][0]['category'])->toBe('GAME')
        ->and($result->data['apps'][1]['is_free'])->toBeFalse();
});

it('fetchDeveloperApps returns failure when scraper returns 500 with FastAPI-style detail', function () {
    Http::fake([
        'http://scraper-android:7463/developers/broken/apps*' => Http::response(['detail' => 'upstream error'], 500),
    ]);

    $result = (new GooglePlayConnector)->fetchDeveloperApps('broken');

    expect($result->success)->toBeFalse()
        ->and($result->error)->toContain('Google Play developer apps fetch failed')
        ->and($result->error)->toContain('upstream error');
});
