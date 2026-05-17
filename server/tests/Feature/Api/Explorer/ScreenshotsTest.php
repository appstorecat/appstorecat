<?php

declare(strict_types=1);

use App\Enums\Platform;
use App\Models\App;
use App\Models\AppVersion;
use App\Models\StoreCategory;
use App\Models\StoreListing;

beforeEach(function () {
    createAuthenticatedUser();
});

/**
 * Attach an en-US listing with one screenshot entry to a freshly-built app.
 */
function explorerScreenshotApp(array $appAttrs = [], array $screenshots = [['url' => 'https://cdn.example.com/s1.png', 'device_type' => 'iphone', 'order' => 0]]): App
{
    $app = App::factory()->create(array_merge(['platform' => Platform::Ios], $appAttrs));
    $version = AppVersion::factory()->create(['app_id' => $app->id]);
    StoreListing::factory()->create([
        'app_id' => $app->id,
        'version_id' => $version->id,
        'locale' => 'en-US',
        'screenshots' => $screenshots,
    ]);

    return $app;
}

it('returns apps with an English listing and non-empty screenshots', function () {
    $withShots = explorerScreenshotApp(
        ['display_name' => 'Has Shots', 'last_synced_at' => now()],
        [
            ['url' => 'https://cdn.example.com/a.png', 'device_type' => 'iphone', 'order' => 0],
            ['url' => 'https://cdn.example.com/b.png', 'device_type' => 'iphone', 'order' => 1],
        ],
    );

    // No listings at all → excluded.
    App::factory()->create(['platform' => Platform::Ios, 'display_name' => 'No Listing']);

    $response = $this->getJson('/api/v1/explorer/screenshots');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.app_id', $withShots->id)
        ->assertJsonPath('data.0.name', 'Has Shots')
        ->assertJsonStructure(['data' => [['app_id', 'external_id', 'platform', 'name', 'screenshots']], 'links', 'meta'])
        ->assertJsonPath('data.0.screenshots.0.url', 'https://cdn.example.com/a.png');
});

it('filters screenshot explorer by platform', function () {
    $ios = explorerScreenshotApp(['platform' => Platform::Ios, 'display_name' => 'iOS']);

    $android = App::factory()->android()->create(['display_name' => 'Android']);
    $v = AppVersion::factory()->create(['app_id' => $android->id]);
    StoreListing::factory()->create([
        'app_id' => $android->id,
        'version_id' => $v->id,
        'locale' => 'en-US',
        'screenshots' => [['url' => 'https://cdn.example.com/x.png', 'device_type' => 'phone', 'order' => 0]],
    ]);

    $this->getJson('/api/v1/explorer/screenshots?platform=ios')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.app_id', $ios->id);

    $this->getJson('/api/v1/explorer/screenshots?platform=android')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.app_id', $android->id);
});

it('filters screenshot explorer by category_id', function () {
    $category = StoreCategory::platform('ios')->where('type', 'app')->whereNotNull('external_id')->first();
    $otherCategory = StoreCategory::platform('ios')->where('type', 'app')->whereNotNull('external_id')
        ->where('id', '!=', $category->id)->first();

    $match = explorerScreenshotApp(['category_id' => $category->id]);
    explorerScreenshotApp(['category_id' => $otherCategory->id]);

    $this->getJson('/api/v1/explorer/screenshots?category_id='.$category->id)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.app_id', $match->id);
});

it('filters screenshot explorer by search against the listing title', function () {
    $fooApp = App::factory()->create(['platform' => Platform::Ios, 'display_name' => 'Foo App']);
    $fooVersion = AppVersion::factory()->create(['app_id' => $fooApp->id]);
    StoreListing::factory()->create([
        'app_id' => $fooApp->id,
        'version_id' => $fooVersion->id,
        'locale' => 'en-US',
        'title' => 'FooFancy Title',
        'screenshots' => [['url' => 'https://cdn.example.com/a.png', 'device_type' => 'iphone', 'order' => 0]],
    ]);

    $barApp = App::factory()->create(['platform' => Platform::Ios, 'display_name' => 'Bar App']);
    $barVersion = AppVersion::factory()->create(['app_id' => $barApp->id]);
    StoreListing::factory()->create([
        'app_id' => $barApp->id,
        'version_id' => $barVersion->id,
        'locale' => 'en-US',
        'title' => 'Bar Plain',
        'screenshots' => [['url' => 'https://cdn.example.com/b.png', 'device_type' => 'iphone', 'order' => 0]],
    ]);

    $this->getJson('/api/v1/explorer/screenshots?search=FooFancy')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.app_id', $fooApp->id);
});

it('paginates screenshots with per_page and supports page=2', function () {
    foreach (range(1, 5) as $i) {
        explorerScreenshotApp([
            'display_name' => "Shot App {$i}",
            'last_synced_at' => now()->subMinutes($i),
        ], [['url' => "https://cdn.example.com/s{$i}.png", 'device_type' => 'iphone', 'order' => 0]]);
    }

    $page1 = $this->getJson('/api/v1/explorer/screenshots?per_page=2');
    $page1->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.per_page', 2)
        ->assertJsonPath('meta.total', 5)
        ->assertJsonStructure(['data', 'links', 'meta' => ['current_page', 'per_page', 'total', 'last_page']]);

    $page2 = $this->getJson('/api/v1/explorer/screenshots?per_page=2&page=2');
    $page2->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.current_page', 2);

    $page3 = $this->getJson('/api/v1/explorer/screenshots?per_page=2&page=3');
    $page3->assertOk()
        ->assertJsonCount(1, 'data');
});
