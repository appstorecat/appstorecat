<?php

uses()->group('integration');

use App\Connectors\ITunesLookupConnector;
use App\Models\App;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
    $this->connector = new ITunesLookupConnector;
    $this->fixture = file_get_contents(base_path('tests/fixtures/itunes_lookup_response.json'));
});

test('fetches identity from itunes api', function () {
    Http::fake(['itunes.apple.com/*' => Http::response(json_decode($this->fixture, true))]);

    $app = App::factory()->create(['external_id' => 'com.facebook.Facebook']);
    $result = $this->connector->fetchIdentity($app);

    expect($result->success)->toBeTrue()
        ->and($result->data['name'])->toBe('Facebook')
        ->and($result->data['publisher_name'])->toBe('Meta Platforms, Inc.')
        ->and($result->data['publisher_external_id'])->toBe('284882218')
        ->and($result->data['external_id'])->toBe('284882215')
        ->and($result->data['version'])->toBe('450.0')
        ->and($result->data['current_version_release_date'])->toBe('2026-03-15');
});

test('fetches listings from itunes api', function () {
    Http::fake(['itunes.apple.com/*' => Http::response(json_decode($this->fixture, true))]);

    $app = App::factory()->create(['external_id' => 'com.facebook.Facebook']);
    $result = $this->connector->fetchListings($app);

    expect($result->success)->toBeTrue()
        ->and($result->data['title'])->toBe('Facebook')
        ->and($result->data['locale'])->toBe('en_US')
        ->and($result->data['screenshots'])->toHaveCount(3);
});

test('fetches localized listings for supported locales', function () {
    Http::fake(['itunes.apple.com/*' => Http::response(json_decode($this->fixture, true))]);

    $app = App::factory()->create([
        'external_id' => 'com.facebook.Facebook',
        'supported_locales' => ['EN', 'TR', 'DE'],
    ]);

    $results = $this->connector->fetchLocalizedListings($app);

    expect($results)->toHaveCount(2)
        ->and($results[0]->data['locale'])->toBe('tr_TR')
        ->and($results[1]->data['locale'])->toBe('de_DE');
});

test('fetches metrics from itunes api', function () {
    Http::fake(['itunes.apple.com/*' => Http::response(json_decode($this->fixture, true))]);

    $app = App::factory()->create(['external_id' => 'com.facebook.Facebook']);
    $result = $this->connector->fetchMetrics($app);

    expect($result->success)->toBeTrue()
        ->and((float) $result->data['rating'])->toBe(4.0)
        ->and($result->data['rating_count'])->toBe(15000000);
});

test('handles api failure gracefully', function () {
    Http::fake(['itunes.apple.com/*' => Http::response([], 500)]);

    $app = App::factory()->create(['external_id' => 'com.nonexistent.app']);
    $result = $this->connector->fetchIdentity($app);

    expect($result->success)->toBeFalse();
});

test('handles empty results', function () {
    Http::fake(['itunes.apple.com/*' => Http::response(['resultCount' => 0, 'results' => []])]);

    $app = App::factory()->create(['external_id' => 'com.nonexistent.app']);
    $result = $this->connector->fetchIdentity($app);

    expect($result->success)->toBeFalse()
        ->and($result->error)->toContain('No results found');
});
