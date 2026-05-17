<?php

declare(strict_types=1);

use App\Enums\Platform;
use App\Models\App;
use App\Models\AppCompetitor;
use App\Models\AppVersion;
use App\Models\StoreListing;

beforeEach(function () {
    $this->user = createAuthenticatedUser();
});

/**
 * Create a StoreListing for the given app/version/locale. Provides text rich
 * enough that the n-gram analyzer returns non-trivial rows for ngram=1/2/3.
 */
function makeListing(App $app, ?AppVersion $version = null, string $locale = 'en-US', array $overrides = []): StoreListing
{
    $version ??= AppVersion::factory()->create(['app_id' => $app->id]);

    return StoreListing::create(array_merge([
        'app_id' => $app->id,
        'version_id' => $version->id,
        'locale' => $locale,
        'title' => 'Photo Editor Studio',
        'subtitle' => 'Photo Editor Pro',
        'description' => 'Photo editor photo editor studio. Edit photos with professional photo editor tools and filters.',
        'whats_new' => 'New photo editor features added.',
        'price' => 0,
        'currency' => null,
        'fetched_at' => now(),
        'checksum' => 'check-'.uniqid(),
    ], $overrides));
}

it('returns n-gram keyword density for an app listing', function () {
    $app = App::factory()->create(['platform' => Platform::Ios, 'external_id' => '111']);
    makeListing($app);

    $response = $this->getJson("/api/v1/apps/ios/{$app->external_id}/keywords?ngram=1");

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [['locale', 'ngram_size', 'keyword', 'count', 'density']],
            'meta',
            'links',
        ]);

    $keywords = collect($response->json('data'))->pluck('keyword')->all();
    expect($keywords)->toContain('photo')
        ->and($response->json('data.0.ngram_size'))->toBe(1)
        ->and($response->json('data.0.locale'))->toBe('en-US');
});

it('filters n-gram rows by the requested ngram size', function () {
    $app = App::factory()->create(['platform' => Platform::Ios]);
    makeListing($app);

    $response = $this->getJson("/api/v1/apps/ios/{$app->external_id}/keywords?ngram=2");

    $response->assertOk();
    $sizes = collect($response->json('data'))->pluck('ngram_size')->unique()->all();
    expect($sizes)->toBe([2]);
});

it('sorts density desc by default and respects order=asc', function () {
    $app = App::factory()->create(['platform' => Platform::Ios]);
    makeListing($app);

    $desc = $this->getJson("/api/v1/apps/ios/{$app->external_id}/keywords?ngram=1")->json('data');
    $asc = $this->getJson("/api/v1/apps/ios/{$app->external_id}/keywords?ngram=1&order=asc")->json('data');

    expect($desc[0]['density'])->toBeGreaterThanOrEqual($desc[count($desc) - 1]['density'])
        ->and($asc[0]['density'])->toBeLessThanOrEqual($asc[count($asc) - 1]['density']);
});

it('sorts by count when sort=count is requested', function () {
    $app = App::factory()->create(['platform' => Platform::Ios]);
    makeListing($app);

    $rows = $this->getJson("/api/v1/apps/ios/{$app->external_id}/keywords?ngram=1&sort=count&order=desc")->json('data');

    for ($i = 0; $i < count($rows) - 1; $i++) {
        expect($rows[$i]['count'])->toBeGreaterThanOrEqual($rows[$i + 1]['count']);
    }
});

it('returns an empty paginator when the app has no listing for the requested locale', function () {
    $app = App::factory()->create(['platform' => Platform::Ios]);
    makeListing($app, locale: 'en-US');

    $response = $this->getJson("/api/v1/apps/ios/{$app->external_id}/keywords?locale=tr");

    $response->assertOk()
        ->assertJsonPath('meta.total', 0)
        ->assertJsonCount(0, 'data');
});

it('returns 404 for keywords when the app does not exist', function () {
    $this->getJson('/api/v1/apps/ios/does-not-exist/keywords')
        ->assertStatus(404);
});

it('compares keyword density across competitor apps', function () {
    $app = App::factory()->create(['platform' => Platform::Ios, 'external_id' => '500', 'display_name' => 'Subject App']);
    makeListing($app);

    $competitorA = App::factory()->create(['platform' => Platform::Ios, 'display_name' => 'Rival A']);
    makeListing($competitorA);

    $competitorB = App::factory()->create(['platform' => Platform::Ios, 'display_name' => 'Rival B']);
    makeListing($competitorB);

    AppCompetitor::factory()->create([
        'user_id' => $this->user->id,
        'app_id' => $app->id,
        'competitor_app_id' => $competitorA->id,
    ]);
    AppCompetitor::factory()->create([
        'user_id' => $this->user->id,
        'app_id' => $app->id,
        'competitor_app_id' => $competitorB->id,
    ]);

    $response = $this->getJson("/api/v1/apps/ios/{$app->external_id}/keywords/compare?app_ids[]={$competitorA->id}&app_ids[]={$competitorB->id}&ngram=1");

    $response->assertOk()
        ->assertJsonStructure([
            'apps' => [['id', 'name', 'icon_url', 'versions']],
            'keywords',
        ]);

    $ids = collect($response->json('apps'))->pluck('id')->all();
    expect($ids)->toContain($competitorA->id, $competitorB->id);

    expect($response->json('keywords'))->toHaveKey((string) $competitorA->id);
});

it('returns a non-null name for every app in the compare payload (regression)', function () {
    $app = App::factory()->create(['platform' => Platform::Ios, 'display_name' => 'Subject']);
    makeListing($app);

    // Competitor with display_name set
    $namedRival = App::factory()->create(['platform' => Platform::Ios, 'display_name' => 'Named Rival']);
    makeListing($namedRival);

    // Competitor whose display_name is null — name should fall back to external_id via displayName()
    $unnamedRival = App::factory()->create([
        'platform' => Platform::Ios,
        'display_name' => null,
        'external_id' => 'com.unnamed.rival',
    ]);
    makeListing($unnamedRival);

    AppCompetitor::factory()->create([
        'user_id' => $this->user->id,
        'app_id' => $app->id,
        'competitor_app_id' => $namedRival->id,
    ]);
    AppCompetitor::factory()->create([
        'user_id' => $this->user->id,
        'app_id' => $app->id,
        'competitor_app_id' => $unnamedRival->id,
    ]);

    $response = $this->getJson("/api/v1/apps/ios/{$app->external_id}/keywords/compare?app_ids[]={$namedRival->id}&app_ids[]={$unnamedRival->id}");

    $response->assertOk();

    foreach ($response->json('apps') as $appPayload) {
        expect($appPayload['name'])->not->toBeNull()
            ->and($appPayload['name'])->not->toBe('');
    }

    $byId = collect($response->json('apps'))->keyBy('id');
    expect($byId[$namedRival->id]['name'])->toBe('Named Rival')
        ->and($byId[$unnamedRival->id]['name'])->toBe('com.unnamed.rival');
});

it('rejects compare requests when app_ids contain non-competitors', function () {
    $app = App::factory()->create(['platform' => Platform::Ios]);
    makeListing($app);

    // App that is NOT a competitor of $app for this user.
    $stranger = App::factory()->create(['platform' => Platform::Ios]);

    $this->getJson("/api/v1/apps/ios/{$app->external_id}/keywords/compare?app_ids[]={$stranger->id}")
        ->assertStatus(422);
});
