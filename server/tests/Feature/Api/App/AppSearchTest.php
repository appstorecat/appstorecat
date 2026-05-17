<?php

declare(strict_types=1);

use App\Enums\DiscoverSource;
use App\Models\App;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['appstorecat.connectors.appstore.base_url' => 'http://scraper-ios.test']);
    config(['appstorecat.connectors.gplay.base_url' => 'http://scraper-android.test']);
});

it('rejects search when unauthenticated', function () {
    $this->getJson('/api/v1/apps/search?term=instagram&platform=ios&country_code=us')
        ->assertStatus(401);
});

it('returns search results from the iOS scraper and persists them', function () {
    createAuthenticatedUser();

    Http::fake([
        'http://scraper-ios.test/apps/search*' => Http::response([
            'results' => [
                [
                    'app_id' => '389801252',
                    'name' => 'Instagram',
                    'developer' => 'Instagram, Inc.',
                    'developer_id' => '389801255',
                    'icon_url' => 'https://example.com/insta.png',
                    'genre' => 'Photo & Video',
                    'genre_id' => '6008',
                ],
                [
                    'app_id' => '454638411',
                    'name' => 'Messenger',
                    'developer' => 'Meta',
                    'developer_id' => '284882218',
                    'icon_url' => 'https://example.com/msg.png',
                ],
            ],
        ], 200),
    ]);

    $response = $this->getJson('/api/v1/apps/search?term=instagram&platform=ios&country_code=us');

    $response->assertOk()
        ->assertJsonCount(2)
        ->assertJsonPath('0.external_id', '389801252')
        ->assertJsonPath('0.name', 'Instagram')
        ->assertJsonPath('0.platform', 'ios')
        ->assertJsonPath('1.external_id', '454638411')
        ->assertJsonPath('1.name', 'Messenger');

    expect(App::platform('ios')->where('external_id', '389801252')->exists())->toBeTrue()
        ->and(App::platform('ios')->where('external_id', '454638411')->exists())->toBeTrue();

    $persisted = App::platform('ios')->where('external_id', '389801252')->first();
    expect($persisted->display_name)->toBe('Instagram')
        ->and($persisted->icon_url)->toBe('https://example.com/insta.png')
        ->and($persisted->discovered_from)->toBe(DiscoverSource::Search)
        ->and($persisted->origin_country_code)->toBe('us');
});

it('returns search results from the Android scraper', function () {
    createAuthenticatedUser();

    Http::fake([
        'http://scraper-android.test/apps/search*' => Http::response([
            'results' => [
                [
                    'app_id' => 'com.instagram.android',
                    'name' => 'Instagram',
                    'developer' => 'Instagram',
                    'icon_url' => 'https://example.com/insta-android.png',
                ],
            ],
        ], 200),
    ]);

    $response = $this->getJson('/api/v1/apps/search?term=instagram&platform=android&country_code=us');

    $response->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.external_id', 'com.instagram.android')
        ->assertJsonPath('0.platform', 'android');

    expect(App::platform('android')->where('external_id', 'com.instagram.android')->exists())->toBeTrue();
});

it('filters results via exclude_external_ids', function () {
    createAuthenticatedUser();

    Http::fake([
        'http://scraper-ios.test/apps/search*' => Http::response([
            'results' => [
                ['app_id' => '111', 'name' => 'App One'],
                ['app_id' => '222', 'name' => 'App Two'],
                ['app_id' => '333', 'name' => 'App Three'],
            ],
        ], 200),
    ]);

    $response = $this->getJson('/api/v1/apps/search?term=foo&platform=ios&country_code=us&exclude_external_ids[]=111&exclude_external_ids[]=333');

    $response->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.external_id', '222');

    // The excluded apps were still persisted by discover() — the filter only
    // affects the response payload.
    expect(App::where('external_id', '111')->exists())->toBeTrue()
        ->and(App::where('external_id', '222')->exists())->toBeTrue()
        ->and(App::where('external_id', '333')->exists())->toBeTrue();
});

it('rejects search with a term shorter than 2 characters', function () {
    createAuthenticatedUser();

    $this->getJson('/api/v1/apps/search?term=a&platform=ios&country_code=us')
        ->assertStatus(422)
        ->assertJsonValidationErrors(['term']);
});

it('rejects search with an invalid platform', function () {
    createAuthenticatedUser();

    $this->getJson('/api/v1/apps/search?term=instagram&platform=windows&country_code=us')
        ->assertStatus(422)
        ->assertJsonValidationErrors(['platform']);
});

it('skips scraper results that arrive without an app_id (regression)', function () {
    createAuthenticatedUser();

    Http::fake([
        'http://scraper-ios.test/apps/search*' => Http::response([
            'results' => [
                ['app_id' => '', 'name' => 'Bogus'],
                ['name' => 'Missing key'],
                ['app_id' => '999', 'name' => 'Real App'],
            ],
        ], 200),
    ]);

    $response = $this->getJson('/api/v1/apps/search?term=foo&platform=ios&country_code=us');

    $response->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.external_id', '999');

    expect(App::where('external_id', '')->exists())->toBeFalse()
        ->and(App::whereNull('external_id')->exists())->toBeFalse()
        ->and(App::where('external_id', '999')->exists())->toBeTrue();
});
