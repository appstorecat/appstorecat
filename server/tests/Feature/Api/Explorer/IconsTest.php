<?php

declare(strict_types=1);

use App\Enums\Platform;
use App\Models\App;
use App\Models\StoreCategory;

beforeEach(function () {
    createAuthenticatedUser();
});

/**
 * Create an app with an icon_url directly on the apps row. The explorer icons
 * endpoint reads apps.icon_url directly (no listing join), so this is all the
 * fixture needs.
 */
function explorerIconApp(array $appAttrs = [], ?string $iconUrl = 'https://cdn.example.com/icon.png'): App
{
    return App::factory()->create(array_merge(
        ['platform' => Platform::Ios, 'icon_url' => $iconUrl],
        $appAttrs,
    ));
}

it('returns apps with a non-null icon_url', function () {
    $withIcon = explorerIconApp(
        ['display_name' => 'Has Icon', 'discovered_at' => now()],
        'https://cdn.example.com/has-icon.png',
    );

    // App with icon_url null → excluded.
    App::factory()->create([
        'platform' => Platform::Ios,
        'display_name' => 'No Icon',
        'icon_url' => null,
    ]);

    $response = $this->getJson('/api/v1/explorer/icons');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.app_id', $withIcon->id)
        ->assertJsonPath('data.0.icon_url', 'https://cdn.example.com/has-icon.png')
        ->assertJsonPath('data.0.name', 'Has Icon')
        ->assertJsonStructure(['data', 'links', 'meta']);
});

it('filters icon explorer by platform', function () {
    $ios = explorerIconApp(['platform' => Platform::Ios, 'display_name' => 'iOS App']);

    $android = App::factory()->android()->create([
        'display_name' => 'Android App',
        'icon_url' => 'https://cdn.example.com/and.png',
    ]);

    $this->getJson('/api/v1/explorer/icons?platform=ios')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.app_id', $ios->id);

    $this->getJson('/api/v1/explorer/icons?platform=android')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.app_id', $android->id);
});

it('filters icon explorer by category_id', function () {
    $category = StoreCategory::platform('ios')->where('type', 'app')->whereNotNull('external_id')->first();
    $otherCategory = StoreCategory::platform('ios')->where('type', 'app')->whereNotNull('external_id')
        ->where('id', '!=', $category->id)->first();

    $match = explorerIconApp(['category_id' => $category->id]);
    explorerIconApp(['category_id' => $otherCategory->id]);

    $this->getJson('/api/v1/explorer/icons?category_id='.$category->id)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.app_id', $match->id);
});

it('filters icon explorer by search against display_name', function () {
    $foo = explorerIconApp(['display_name' => 'Foo App']);
    explorerIconApp(['display_name' => 'Bar App']);

    $this->getJson('/api/v1/explorer/icons?search=foo')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.app_id', $foo->id);
});

it('paginates icons with per_page and supports page=2 (infinite-scroll friendly)', function () {
    foreach (range(1, 5) as $i) {
        explorerIconApp([
            'display_name' => "App {$i}",
            'discovered_at' => now()->subMinutes($i),
        ], "https://cdn.example.com/{$i}.png");
    }

    $page1 = $this->getJson('/api/v1/explorer/icons?per_page=2');
    $page1->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.per_page', 2)
        ->assertJsonPath('meta.total', 5)
        ->assertJsonStructure(['data', 'links', 'meta' => ['current_page', 'per_page', 'total', 'last_page']]);

    $page2 = $this->getJson('/api/v1/explorer/icons?per_page=2&page=2');
    $page2->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.current_page', 2);

    $page3 = $this->getJson('/api/v1/explorer/icons?per_page=2&page=3');
    $page3->assertOk()
        ->assertJsonCount(1, 'data');
});
