<?php

declare(strict_types=1);

use App\Connectors\GooglePlayConnector;
use App\Connectors\ITunesLookupConnector;
use App\Enums\ChartCollection;
use App\Enums\Platform;
use App\Jobs\Chart\FetchChartSnapshotJob;
use App\Models\App;
use App\Models\ChartEntry;
use App\Models\ChartSnapshot;
use App\Models\StoreCategory;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('appstorecat.connectors.appstore.base_url', 'http://scraper-ios.test');
    config()->set('appstorecat.connectors.appstore.timeout', 30);
    config()->set('appstorecat.connectors.gplay.base_url', 'http://scraper-android.test');
    config()->set('appstorecat.connectors.gplay.timeout', 30);
    config()->set('appstorecat.discover.ios.on_trending', true);
    config()->set('appstorecat.discover.android.on_trending', true);
});

/*
|--------------------------------------------------------------------------
| Contract / Class metadata
|--------------------------------------------------------------------------
| Unlike SyncChartSnapshotJob, the UI-triggered FetchChartSnapshotJob is NOT
| ShouldBeUnique — it's invoked synchronously from ChartController::index
| to make stale charts self-heal on first user request.
|--------------------------------------------------------------------------
*/

it('implements ShouldQueue but NOT ShouldBeUnique', function () {
    $job = new FetchChartSnapshotJob('ios', ChartCollection::TopFree->value, 'us', 1);

    expect($job)->toBeInstanceOf(ShouldQueue::class)
        ->and($job)->not->toBeInstanceOf(ShouldBeUnique::class);
});

it('exposes the UI-triggered tries+backoff configuration', function () {
    $job = new FetchChartSnapshotJob('ios', ChartCollection::TopFree->value, 'us', 1);

    expect($job->tries)->toBe(3)
        ->and($job->backoff)->toBe([30, 60, 120]);
});

/*
|--------------------------------------------------------------------------
| dispatchSync (UI-triggered) — runs in-process, no queue interaction
|--------------------------------------------------------------------------
*/

it('dispatchSync fetches the chart, creates a snapshot and discovers apps', function () {
    $category = StoreCategory::where('platform', Platform::Ios)
        ->whereNotNull('external_id')
        ->first();
    expect($category)->not->toBeNull();

    Http::fake([
        'http://scraper-ios.test/charts*' => Http::response([
            'results' => [
                ['app_id' => '333', 'rank' => 1, 'name' => 'Three', 'price' => 0, 'currency' => 'USD'],
                ['app_id' => '444', 'rank' => 2, 'name' => 'Four', 'price' => 0, 'currency' => 'USD'],
            ],
        ], 200),
    ]);

    FetchChartSnapshotJob::dispatchSync('ios', ChartCollection::TopFree->value, 'us', $category->id);

    $snapshot = ChartSnapshot::forChart('ios', ChartCollection::TopFree->value, 'us', $category->id)->first();

    expect($snapshot)->not->toBeNull()
        ->and(ChartEntry::where('trending_chart_id', $snapshot->id)->count())->toBe(2)
        ->and(App::where('external_id', '333')->exists())->toBeTrue()
        ->and(App::where('external_id', '444')->exists())->toBeTrue();
});

it('dispatchSync is a no-op when a snapshot already exists for today', function () {
    $category = StoreCategory::where('platform', Platform::Ios)->first();

    ChartSnapshot::create([
        'platform' => Platform::Ios,
        'collection' => ChartCollection::TopFree,
        'category_id' => $category->id,
        'country_code' => 'us',
        'snapshot_date' => now()->toDateString(),
    ]);

    Http::fake();

    FetchChartSnapshotJob::dispatchSync('ios', ChartCollection::TopFree->value, 'us', $category->id);

    Http::assertNothingSent();
    expect(ChartSnapshot::count())->toBe(1);
});

it('dispatchSync logs a warning and creates no snapshot when results are empty', function () {
    $category = StoreCategory::where('platform', Platform::Ios)->first();

    Http::fake([
        'http://scraper-ios.test/charts*' => Http::response(['results' => []], 200),
    ]);

    FetchChartSnapshotJob::dispatchSync('ios', ChartCollection::TopFree->value, 'us', $category->id);

    expect(ChartSnapshot::count())->toBe(0)
        ->and(ChartEntry::count())->toBe(0);
});

it('routes to the GooglePlay connector when invoked for android', function () {
    $category = StoreCategory::where('platform', Platform::Android)->first();
    expect($category)->not->toBeNull();

    $ios = Mockery::mock(ITunesLookupConnector::class);
    $ios->shouldNotReceive('fetchChart');

    $android = Mockery::mock(GooglePlayConnector::class);
    $android->shouldReceive('fetchChart')
        ->once()
        ->andReturn([
            ['app_id' => 'com.fetch.alpha', 'rank' => 1, 'name' => 'Alpha', 'price' => 0],
        ]);

    (new FetchChartSnapshotJob('android', ChartCollection::TopFree->value, 'us', $category->id))
        ->handle($ios, $android);

    expect(ChartSnapshot::where('platform', Platform::Android->value)->count())->toBe(1)
        ->and(App::where('external_id', 'com.fetch.alpha')->exists())->toBeTrue();
});

it('does not create new App rows when DiscoverSource::Trending is disabled for the platform', function () {
    config()->set('appstorecat.discover.ios.on_trending', false);

    $category = StoreCategory::where('platform', Platform::Ios)
        ->whereNotNull('external_id')
        ->first();

    Http::fake([
        'http://scraper-ios.test/charts*' => Http::response([
            'results' => [
                ['app_id' => '999', 'rank' => 1, 'name' => 'Nine', 'price' => 0],
            ],
        ], 200),
    ]);

    FetchChartSnapshotJob::dispatchSync('ios', ChartCollection::TopFree->value, 'us', $category->id);

    expect(App::where('external_id', '999')->exists())->toBeFalse()
        // Snapshot row still gets created (chart-level metadata), but no
        // entries because no app row was discovered.
        ->and(ChartEntry::count())->toBe(0);
});
