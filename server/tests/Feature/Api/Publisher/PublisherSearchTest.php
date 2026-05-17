<?php

declare(strict_types=1);

use App\Enums\Platform;
use App\Models\App;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    createAuthenticatedUser();
});

it('groups iOS scraper search results by publisher', function () {
    Http::fake([
        '*/apps/search*' => Http::response([
            'results' => [
                [
                    'app_id' => '111111',
                    'name' => 'Meta App One',
                    'developer' => 'Meta Platforms, Inc.',
                    'developer_id' => '389801255',
                    'icon_url' => 'https://example.com/one.png',
                ],
                [
                    'app_id' => '222222',
                    'name' => 'Meta App Two',
                    'developer' => 'Meta Platforms, Inc.',
                    'developer_id' => '389801255',
                    'icon_url' => 'https://example.com/two.png',
                ],
                [
                    'app_id' => '333333',
                    'name' => 'Other App',
                    'developer' => 'Other Studio',
                    'developer_id' => '999',
                ],
            ],
        ], 200),
    ]);

    $response = $this->getJson('/api/v1/publishers/search?term=meta&platform=ios&country_code=us');

    $response->assertOk()
        ->assertJsonCount(2);

    $meta = collect($response->json())->firstWhere('external_id', '389801255');
    expect($meta)->not->toBeNull()
        ->and($meta['name'])->toBe('Meta Platforms, Inc.')
        ->and($meta['app_count'])->toBe(2)
        ->and($meta['platform'])->toBe('ios')
        ->and($meta['sample_apps'])->toHaveCount(2);
});

it('rejects search when term is shorter than 2 characters', function () {
    $response = $this->getJson('/api/v1/publishers/search?term=a&platform=ios');

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['term']);
});

it('rejects search when term is missing', function () {
    $response = $this->getJson('/api/v1/publishers/search?platform=ios');

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['term']);
});

it('rejects search when platform is missing', function () {
    $response = $this->getJson('/api/v1/publishers/search?term=foo');

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['platform']);
});

it('rejects search when platform is not ios or android', function () {
    $response = $this->getJson('/api/v1/publishers/search?term=foo&platform=windows');

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['platform']);
});

it('returns an empty list when the scraper throws', function () {
    Http::fake([
        '*/apps/search*' => Http::response(['error' => 'boom'], 500),
    ]);

    $response = $this->getJson('/api/v1/publishers/search?term=meta&platform=ios');

    $response->assertOk()->assertJsonCount(0);
});

it('hits the Google Play scraper when platform is android', function () {
    Http::fake([
        '*/apps/search*' => Http::response([
            'results' => [
                [
                    'app_id' => 'com.example.one',
                    'name' => 'Example One',
                    'developer' => 'Example Studio',
                    'developer_id' => 'Example+Studio',
                    'icon_url' => 'https://example.com/one.png',
                ],
            ],
        ], 200),
    ]);

    $response = $this->getJson('/api/v1/publishers/search?term=example&platform=android');

    $response->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.platform', 'android')
        ->assertJsonPath('0.name', 'Example Studio');
});

it('discovers apps in the catalog as a side effect of search', function () {
    Http::fake([
        '*/apps/search*' => Http::response([
            'results' => [
                [
                    'app_id' => '777777',
                    'name' => 'Discovered App',
                    'developer' => 'Some Publisher',
                    'developer_id' => '12345',
                    'icon_url' => 'https://example.com/d.png',
                ],
            ],
        ], 200),
    ]);

    expect(App::where('external_id', '777777')->exists())->toBeFalse();

    $this->getJson('/api/v1/publishers/search?term=disc&platform=ios')->assertOk();

    expect(
        App::platform(Platform::Ios)->where('external_id', '777777')->exists()
    )->toBeTrue();
});

it('skips results that have no developer name', function () {
    Http::fake([
        '*/apps/search*' => Http::response([
            'results' => [
                ['app_id' => '1', 'name' => 'No Dev', 'developer' => ''],
                ['app_id' => '2', 'name' => 'Has Dev', 'developer' => 'Real Dev', 'developer_id' => '42'],
            ],
        ], 200),
    ]);

    $response = $this->getJson('/api/v1/publishers/search?term=test&platform=ios');

    $response->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.name', 'Real Dev');
});

it('rejects an unknown country_code', function () {
    $response = $this->getJson('/api/v1/publishers/search?term=foo&platform=ios&country_code=qq');

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['country_code']);
});
