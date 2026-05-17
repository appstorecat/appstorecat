<?php

declare(strict_types=1);

use App\Enums\ChartCollection;
use App\Enums\Platform;
use App\Jobs\Chart\FetchChartSnapshotJob;
use App\Models\App;
use App\Models\ChartEntry;
use App\Models\ChartSnapshot;
use App\Models\Publisher;
use App\Models\StoreCategory;
use Illuminate\Support\Facades\Bus;

beforeEach(function () {
    createAuthenticatedUser();
    // Prevent the controller's auto-dispatchSync from actually hitting the
    // store connectors when no fresh snapshot is present.
    Bus::fake([FetchChartSnapshotJob::class]);
});

it('returns chart entries for the requested chart with meta info', function () {
    $category = StoreCategory::platform('ios')->where('external_id', '6005')->first()
        ?? StoreCategory::platform('ios')->where('type', 'app')->whereNotNull('external_id')->first();

    expect($category)->not->toBeNull();

    $publisher = Publisher::factory()->create();
    $app = App::factory()->create([
        'platform' => Platform::Ios,
        'display_name' => 'Foo App',
        'publisher_id' => $publisher->id,
        'category_id' => $category->id,
    ]);

    $snapshot = ChartSnapshot::factory()->create([
        'platform' => Platform::Ios,
        'collection' => ChartCollection::TopFree,
        'country_code' => 'us',
        'category_id' => $category->id,
        'snapshot_date' => now()->toDateString(),
    ]);

    ChartEntry::factory()->create([
        'trending_chart_id' => $snapshot->id,
        'app_id' => $app->id,
        'rank' => 1,
        'price' => 0,
        'currency' => 'USD',
    ]);

    $response = $this->getJson(
        '/api/v1/charts?platform=ios&collection=top_free&country_code=us&category_id='.$category->id,
    );

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.rank', 1)
        ->assertJsonPath('data.0.app_id', $app->id)
        ->assertJsonPath('data.0.app_name', 'Foo App')
        ->assertJsonPath('data.0.platform', 'ios')
        ->assertJsonPath('data.0.is_free', true)
        ->assertJsonPath('meta.snapshot_date', $snapshot->snapshot_date->toDateString())
        ->assertJsonPath('meta.platform', 'ios')
        ->assertJsonPath('meta.collection', 'top_free')
        ->assertJsonPath('meta.country_code', 'us')
        ->assertJsonStructure(['data', 'meta' => ['snapshot_date', 'updated_at', 'platform', 'collection', 'country_code']]);

    // A today-dated snapshot exists → no fetch should be dispatched.
    Bus::assertNotDispatchedSync(FetchChartSnapshotJob::class);
});

it('returns data: [] with a message when no snapshot exists at all', function () {
    $category = StoreCategory::platform('ios')->where('type', 'app')->whereNotNull('external_id')->first();

    $response = $this->getJson(
        '/api/v1/charts?platform=ios&collection=top_free&country_code=us&category_id='.$category->id,
    );

    $response->assertOk()
        ->assertJsonPath('data', [])
        ->assertJsonPath('meta.snapshot_date', null)
        ->assertJsonPath('meta.updated_at', null)
        ->assertJsonPath('meta.platform', 'ios')
        ->assertJsonPath('meta.collection', 'top_free')
        ->assertJsonPath('meta.country_code', 'us')
        ->assertJsonPath('meta.message', 'No chart data available.');

    // Stale (no snapshot) → controller should attempt a fetch.
    Bus::assertDispatchedSync(FetchChartSnapshotJob::class);
});

it('computes rank_change against the previous-day snapshot', function () {
    $category = StoreCategory::platform('ios')->where('type', 'app')->whereNotNull('external_id')->first();

    $app1 = App::factory()->create(['platform' => Platform::Ios, 'display_name' => 'Climber']);
    $app2 = App::factory()->create(['platform' => Platform::Ios, 'display_name' => 'Faller']);
    $app3 = App::factory()->create(['platform' => Platform::Ios, 'display_name' => 'NewEntry']);

    // Yesterday's snapshot
    $yesterday = ChartSnapshot::factory()->create([
        'platform' => Platform::Ios,
        'collection' => ChartCollection::TopFree,
        'country_code' => 'us',
        'category_id' => $category->id,
        'snapshot_date' => now()->subDay()->toDateString(),
    ]);
    ChartEntry::factory()->create([
        'trending_chart_id' => $yesterday->id,
        'app_id' => $app1->id,
        'rank' => 5,
    ]);
    ChartEntry::factory()->create([
        'trending_chart_id' => $yesterday->id,
        'app_id' => $app2->id,
        'rank' => 2,
    ]);

    // Today's snapshot — app1 climbed 5→1, app2 fell 2→3, app3 is brand new.
    $today = ChartSnapshot::factory()->create([
        'platform' => Platform::Ios,
        'collection' => ChartCollection::TopFree,
        'country_code' => 'us',
        'category_id' => $category->id,
        'snapshot_date' => now()->toDateString(),
    ]);
    ChartEntry::factory()->create(['trending_chart_id' => $today->id, 'app_id' => $app1->id, 'rank' => 1]);
    ChartEntry::factory()->create(['trending_chart_id' => $today->id, 'app_id' => $app2->id, 'rank' => 3]);
    ChartEntry::factory()->create(['trending_chart_id' => $today->id, 'app_id' => $app3->id, 'rank' => 7]);

    $response = $this->getJson(
        '/api/v1/charts?platform=ios&collection=top_free&country_code=us&category_id='.$category->id,
    );

    $response->assertOk()->assertJsonCount(3, 'data');

    $entries = collect($response->json('data'))->keyBy('app_id');

    expect($entries[$app1->id]['rank_change'])->toBe(4);   // 5 - 1
    expect($entries[$app2->id]['rank_change'])->toBe(-1);  // 2 - 3
    expect($entries[$app3->id]['rank_change'])->toBeNull(); // not on yesterday
});

it('falls back to the platform "All" category when category_id is omitted', function () {
    $allCategory = StoreCategory::platform('ios')
        ->whereNull('external_id')
        ->where('type', 'app')
        ->first();

    expect($allCategory)->not->toBeNull();

    $app = App::factory()->create(['platform' => Platform::Ios, 'display_name' => 'AllCat App']);
    $snapshot = ChartSnapshot::factory()->create([
        'platform' => Platform::Ios,
        'collection' => ChartCollection::TopFree,
        'country_code' => 'us',
        'category_id' => $allCategory->id,
        'snapshot_date' => now()->toDateString(),
    ]);
    ChartEntry::factory()->create([
        'trending_chart_id' => $snapshot->id,
        'app_id' => $app->id,
        'rank' => 1,
    ]);

    $response = $this->getJson('/api/v1/charts?platform=ios&collection=top_free&country_code=us');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.app_id', $app->id);
});

it('defaults country_code to "us" when not provided', function () {
    $category = StoreCategory::platform('ios')->whereNull('external_id')->where('type', 'app')->first();

    $app = App::factory()->create(['platform' => Platform::Ios]);
    $snapshot = ChartSnapshot::factory()->create([
        'platform' => Platform::Ios,
        'collection' => ChartCollection::TopFree,
        'country_code' => 'us',
        'category_id' => $category->id,
        'snapshot_date' => now()->toDateString(),
    ]);
    ChartEntry::factory()->create([
        'trending_chart_id' => $snapshot->id,
        'app_id' => $app->id,
        'rank' => 1,
    ]);

    $response = $this->getJson('/api/v1/charts?platform=ios&collection=top_free');

    $response->assertOk()
        ->assertJsonPath('meta.country_code', 'us');
});

it('rejects requests missing required platform/collection parameters', function () {
    $this->getJson('/api/v1/charts')
        ->assertStatus(422)
        ->assertJsonValidationErrors(['platform', 'collection']);
});
