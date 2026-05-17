<?php

declare(strict_types=1);

use App\Connectors\ITunesLookupConnector;
use App\Enums\Platform;
use App\Models\App;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('appstorecat.connectors.appstore.base_url', 'http://scraper-ios:7462');
    config()->set('appstorecat.connectors.appstore.timeout', 30);
});

/*
|--------------------------------------------------------------------------
| fetchIdentity
|--------------------------------------------------------------------------
*/

it('fetchIdentity maps a successful identity response into ConnectorResult::success', function () {
    $app = App::factory()->create([
        'platform' => Platform::Ios,
        'external_id' => '12345',
    ]);

    Http::fake([
        'http://scraper-ios:7462/apps/12345/identity*' => Http::response([
            'app_id' => '12345',
            'name' => 'Example App',
            'publisher_name' => 'Example Inc',
            'publisher_external_id' => 'pub-9',
            'publisher_url' => 'https://example.com',
            'category' => 'Productivity',
            'category_id' => '6007',
            'content_rating' => '4+',
            'supported_locales' => ['en', 'tr'],
            'original_release_date' => '2020-01-15T00:00:00Z',
            'price_model' => 'free',
            'version' => '3.4.1',
            'current_version_release_date' => '2026-02-01T00:00:00Z',
        ], 200),
    ]);

    $connector = new ITunesLookupConnector;
    $result = $connector->fetchIdentity($app, 'us');

    expect($result->success)->toBeTrue()
        ->and($result->data['external_id'])->toBe('12345')
        ->and($result->data['name'])->toBe('Example App')
        ->and($result->data['publisher_name'])->toBe('Example Inc')
        ->and($result->data['publisher_external_id'])->toBe('pub-9')
        ->and($result->data['publisher_url'])->toBe('https://example.com')
        ->and($result->data['category_primary'])->toBe('Productivity')
        ->and($result->data['category_external_id'])->toBe('6007')
        ->and($result->data['content_rating'])->toBe('4+')
        ->and($result->data['supported_locales'])->toBe(['en', 'tr'])
        ->and($result->data['original_release_date'])->toBe('2020-01-15')
        ->and($result->data['is_free'])->toBeTrue()
        ->and($result->data['version'])->toBe('3.4.1')
        ->and($result->data['current_version_release_date'])->toBe('2026-02-01');

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/apps/12345/identity')
            && str_contains($request->url(), 'country=us');
    });
});

it('fetchIdentity returns failure on 404', function () {
    $app = App::factory()->create(['external_id' => 'missing']);

    Http::fake([
        'http://scraper-ios:7462/apps/missing/identity*' => Http::response(['error' => 'not found'], 404),
    ]);

    $result = (new ITunesLookupConnector)->fetchIdentity($app, 'us');

    expect($result->success)->toBeFalse()
        ->and($result->error)->toContain('App Store fetch failed');
});

it('fetchIdentity returns failure on HTTP 500', function () {
    $app = App::factory()->create(['external_id' => '500app']);

    Http::fake([
        'http://scraper-ios:7462/apps/500app/identity*' => Http::response(['error' => 'boom'], 500),
    ]);

    $result = (new ITunesLookupConnector)->fetchIdentity($app, 'us');

    expect($result->success)->toBeFalse()
        ->and($result->error)->toContain('App Store fetch failed')
        ->and($result->error)->toContain('500');
});

it('fetchIdentity returns failure on empty/null body', function () {
    $app = App::factory()->create(['external_id' => 'empty']);

    // null JSON body with a failed status code → connector throws, returns failure.
    Http::fake([
        'http://scraper-ios:7462/apps/empty/identity*' => Http::response(null, 500),
    ]);

    $result = (new ITunesLookupConnector)->fetchIdentity($app, 'us');

    expect($result->success)->toBeFalse()
        ->and($result->error)->toContain('App Store fetch failed');
});

it('fetchIdentity returns null for unparseable date strings (parseDate regression)', function () {
    $app = App::factory()->create(['external_id' => 'bad-dates']);

    Http::fake([
        'http://scraper-ios:7462/apps/bad-dates/identity*' => Http::response([
            'app_id' => 'bad-dates',
            'name' => 'Bad Dates App',
            'original_release_date' => 'not-a-real-date',
            'current_version_release_date' => '   ',
            'price_model' => 'free',
        ], 200),
    ]);

    $result = (new ITunesLookupConnector)->fetchIdentity($app, 'us');

    expect($result->success)->toBeTrue()
        ->and($result->data['original_release_date'])->toBeNull()
        ->and($result->data['current_version_release_date'])->toBeNull();
});

/*
|--------------------------------------------------------------------------
| fetchListings
|--------------------------------------------------------------------------
*/

it('fetchListings routes locale via lang query and maps screenshots array', function () {
    $app = App::factory()->create(['external_id' => 'listed']);

    Http::fake([
        'http://scraper-ios:7462/apps/listed/listings*' => Http::response([
            'title' => 'Listed Title',
            'subtitle' => 'Sub line',
            'description' => 'Long description',
            'promotional_text' => 'Promo!',
            'whats_new' => 'Bug fixes',
            'icon_url' => 'https://cdn/icon.png',
            'video_url' => 'https://cdn/video.mp4',
            'price' => 0,
            'currency' => 'USD',
            'screenshots' => [
                ['url' => 'https://cdn/s1.png', 'device_type' => 'iphone', 'order' => 0],
                ['url' => 'https://cdn/s2.png', 'device_type' => 'ipad', 'order' => 1],
            ],
        ], 200),
    ]);

    $result = (new ITunesLookupConnector)->fetchListings($app, 'us', 'en-US');

    expect($result->success)->toBeTrue()
        ->and($result->data['platform'])->toBe('ios')
        ->and($result->data['locale'])->toBe('en-US')
        ->and($result->data['title'])->toBe('Listed Title')
        ->and($result->data['subtitle'])->toBe('Sub line')
        ->and($result->data['icon_url'])->toBe('https://cdn/icon.png')
        ->and($result->data['screenshots'])->toHaveCount(2)
        ->and($result->data['screenshots'][0])->toBe(['url' => 'https://cdn/s1.png', 'device_type' => 'iphone', 'order' => 0])
        ->and($result->data['screenshots'][1]['device_type'])->toBe('ipad');

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/apps/listed/listings')
            && str_contains($request->url(), 'country=us')
            && str_contains($request->url(), 'lang=en-US');
    });
});

it('fetchListings omits lang query when locale is null', function () {
    $app = App::factory()->create(['external_id' => 'no-locale']);

    Http::fake([
        'http://scraper-ios:7462/apps/no-locale/listings*' => Http::response([
            'title' => 'X',
            'screenshots' => [],
        ], 200),
    ]);

    $result = (new ITunesLookupConnector)->fetchListings($app, 'us');

    expect($result->success)->toBeTrue()
        ->and($result->data['locale'])->toBeNull();

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'country=us')
            && ! str_contains($request->url(), 'lang=');
    });
});

/*
|--------------------------------------------------------------------------
| fetchMetrics
|--------------------------------------------------------------------------
*/

it('fetchMetrics maps rating, rating_count and rating_breakdown', function () {
    $app = App::factory()->create(['external_id' => 'metrics']);

    Http::fake([
        'http://scraper-ios:7462/apps/metrics/metrics*' => Http::response([
            'rating' => 4.7,
            'rating_count' => 1234,
            'rating_breakdown' => ['1' => 10, '2' => 5, '3' => 20, '4' => 80, '5' => 1119],
            'file_size_bytes' => 4567890,
        ], 200),
    ]);

    $result = (new ITunesLookupConnector)->fetchMetrics($app, 'us');

    expect($result->success)->toBeTrue()
        ->and($result->data['rating'])->toBe(4.7)
        ->and($result->data['rating_count'])->toBe(1234)
        ->and($result->data['rating_breakdown'])->toBe(['1' => 10, '2' => 5, '3' => 20, '4' => 80, '5' => 1119])
        ->and($result->data['file_size_bytes'])->toBe(4567890);
});

/*
|--------------------------------------------------------------------------
| fetchDeveloperApps
|--------------------------------------------------------------------------
*/

it('fetchDeveloperApps returns the mapped apps array', function () {
    Http::fake([
        'http://scraper-ios:7462/developers/dev-1/apps*' => Http::response([
            'apps' => [
                [
                    'external_id' => 'app-a',
                    'name' => 'App A',
                    'icon_url' => 'https://cdn/a.png',
                    'rating' => 4.5,
                    'rating_count' => 100,
                    'price_model' => 'free',
                    'category' => 'Games',
                ],
                [
                    'external_id' => 'app-b',
                    'name' => 'App B',
                    'price_model' => 'paid',
                ],
            ],
        ], 200),
    ]);

    $result = (new ITunesLookupConnector)->fetchDeveloperApps('dev-1');

    expect($result->success)->toBeTrue()
        ->and($result->data['apps'])->toHaveCount(2)
        ->and($result->data['apps'][0]['external_id'])->toBe('app-a')
        ->and($result->data['apps'][0]['is_free'])->toBeTrue()
        ->and($result->data['apps'][0]['category'])->toBe('Games')
        ->and($result->data['apps'][1]['external_id'])->toBe('app-b')
        ->and($result->data['apps'][1]['is_free'])->toBeFalse()
        ->and($result->data['apps'][1]['category'])->toBeNull();
});

it('fetchDeveloperApps returns failure on HTTP error', function () {
    Http::fake([
        'http://scraper-ios:7462/developers/missing/apps*' => Http::response(['error' => 'nope'], 500),
    ]);

    $result = (new ITunesLookupConnector)->fetchDeveloperApps('missing');

    expect($result->success)->toBeFalse()
        ->and($result->error)->toContain('App Store developer apps fetch failed');
});
