<?php

declare(strict_types=1);

use App\Connectors\GooglePlayConnector;
use App\Connectors\ITunesLookupConnector;
use App\Enums\ChartCollection;
use App\Enums\Platform;
use App\Jobs\Chart\SyncChartSnapshotJob;
use App\Models\App;
use App\Models\ChartEntry;
use App\Models\ChartSnapshot;
use App\Models\StoreCategory;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;

/**
 * Stub Redis::throttle()->allow()->every()->block()->then($cb) so the
 * throttle wrapper invokes the closure immediately without touching Redis.
 */
function stubRedisThrottlePassthrough(): void
{
    $proxy = Mockery::mock();
    $proxy->shouldReceive('allow')->andReturnSelf();
    $proxy->shouldReceive('every')->andReturnSelf();
    $proxy->shouldReceive('block')->andReturnSelf();
    $proxy->shouldReceive('then')->andReturnUsing(function ($cb) {
        $cb();
    });

    Redis::shouldReceive('throttle')->andReturn($proxy);
}

beforeEach(function () {
    config()->set('appstorecat.discover.ios.on_trending', true);
    config()->set('appstorecat.discover.android.on_trending', true);
    config()->set('appstorecat.connectors.appstore.throttle.chart_jobs', 5);
    config()->set('appstorecat.connectors.gplay.throttle.chart_jobs', 5);
});

/*
|--------------------------------------------------------------------------
| Contract / Class metadata
|--------------------------------------------------------------------------
*/

it('implements ShouldQueue and ShouldBeUnique', function () {
    $job = new SyncChartSnapshotJob('ios', ChartCollection::TopFree->value, 'us', 1);

    expect($job)->toBeInstanceOf(ShouldQueue::class)
        ->and($job)->toBeInstanceOf(ShouldBeUnique::class);
});

it('exposes tries=2 + backoff configuration', function () {
    $job = new SyncChartSnapshotJob('ios', ChartCollection::TopFree->value, 'us', 1);

    expect($job->tries)->toBe(2)
        ->and($job->backoff)->toBe([60, 300])
        ->and($job->uniqueFor)->toBe(3600);
});

it('builds a uniqueId scoped to platform:collection:country:category', function () {
    $job = new SyncChartSnapshotJob('ios', 'top_free', 'us', 7);

    expect($job->uniqueId())->toBe('ios:top_free:us:7');
});

/*
|--------------------------------------------------------------------------
| handle() — happy path: fetches, creates snapshot + entries, discovers apps
|--------------------------------------------------------------------------
*/

it('creates a ChartSnapshot and ChartEntry rows, discovering new apps via App::discover', function () {
    stubRedisThrottlePassthrough();

    $category = StoreCategory::where('platform', Platform::Ios)->whereNull('external_id')->first()
        ?? StoreCategory::where('platform', Platform::Ios)->first();

    $ios = Mockery::mock(ITunesLookupConnector::class);
    $ios->shouldReceive('fetchChart')
        ->once()
        ->with(ChartCollection::TopFree->value, 'us', $category->external_id)
        ->andReturn([
            ['app_id' => '111', 'rank' => 1, 'name' => 'Alpha', 'price' => 0, 'currency' => 'USD'],
            ['app_id' => '222', 'rank' => 2, 'name' => 'Beta', 'price' => 0, 'currency' => 'USD'],
        ]);

    $android = Mockery::mock(GooglePlayConnector::class);

    (new SyncChartSnapshotJob('ios', ChartCollection::TopFree->value, 'us', $category->id))
        ->handle($ios, $android);

    $snapshot = ChartSnapshot::where('platform', 1)
        ->where('country_code', 'us')
        ->where('category_id', $category->id)
        ->first();

    expect($snapshot)->not->toBeNull()
        ->and(ChartEntry::where('trending_chart_id', $snapshot->id)->count())->toBe(2)
        ->and(App::where('external_id', '111')->exists())->toBeTrue()
        ->and(App::where('external_id', '222')->exists())->toBeTrue();
});

it('skips creating a snapshot when one already exists for today', function () {
    $category = StoreCategory::where('platform', Platform::Ios)->first();

    ChartSnapshot::create([
        'platform' => Platform::Ios,
        'collection' => ChartCollection::TopFree,
        'category_id' => $category->id,
        'country_code' => 'us',
        'snapshot_date' => now()->toDateString(),
    ]);

    $ios = Mockery::mock(ITunesLookupConnector::class);
    $ios->shouldNotReceive('fetchChart');
    $android = Mockery::mock(GooglePlayConnector::class);

    (new SyncChartSnapshotJob('ios', ChartCollection::TopFree->value, 'us', $category->id))
        ->handle($ios, $android);

    expect(ChartSnapshot::count())->toBe(1);
});

it('skips snapshot creation when the connector returns an empty result set', function () {
    stubRedisThrottlePassthrough();

    $category = StoreCategory::where('platform', Platform::Ios)->first();

    $ios = Mockery::mock(ITunesLookupConnector::class);
    $ios->shouldReceive('fetchChart')->once()->andReturn([]);
    $android = Mockery::mock(GooglePlayConnector::class);

    (new SyncChartSnapshotJob('ios', ChartCollection::TopFree->value, 'us', $category->id))
        ->handle($ios, $android);

    expect(ChartSnapshot::count())->toBe(0)
        ->and(ChartEntry::count())->toBe(0);
});

it('routes the call to the GooglePlay connector on android', function () {
    stubRedisThrottlePassthrough();

    $category = StoreCategory::where('platform', Platform::Android)->first();
    expect($category)->not->toBeNull();

    $ios = Mockery::mock(ITunesLookupConnector::class);
    $ios->shouldNotReceive('fetchChart');

    $android = Mockery::mock(GooglePlayConnector::class);
    $android->shouldReceive('fetchChart')
        ->once()
        ->andReturn([
            ['app_id' => 'com.example.alpha', 'rank' => 1, 'name' => 'Alpha', 'price' => 0],
        ]);

    (new SyncChartSnapshotJob('android', ChartCollection::TopFree->value, 'us', $category->id))
        ->handle($ios, $android);

    expect(ChartSnapshot::where('platform', Platform::Android->value)->count())->toBe(1);
});

it('rethrows when the connector throws and logs a warning', function () {
    stubRedisThrottlePassthrough();

    $category = StoreCategory::where('platform', Platform::Ios)->first();

    $ios = Mockery::mock(ITunesLookupConnector::class);
    $ios->shouldReceive('fetchChart')->once()->andThrow(new RuntimeException('upstream 500'));
    $android = Mockery::mock(GooglePlayConnector::class);

    $job = new SyncChartSnapshotJob('ios', ChartCollection::TopFree->value, 'us', $category->id);

    expect(fn () => $job->handle($ios, $android))
        ->toThrow(RuntimeException::class, 'upstream 500');

    expect(ChartSnapshot::count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Http::fake() smoke test — verifies real connector wiring works end-to-end
|--------------------------------------------------------------------------
*/

it('integrates with the real iTunes connector via Http::fake', function () {
    config()->set('appstorecat.connectors.appstore.base_url', 'http://scraper-ios.test');
    config()->set('appstorecat.connectors.appstore.timeout', 30);

    stubRedisThrottlePassthrough();

    $category = StoreCategory::where('platform', Platform::Ios)
        ->whereNotNull('external_id')
        ->first();
    expect($category)->not->toBeNull();

    Http::fake([
        'http://scraper-ios.test/charts*' => Http::response([
            'results' => [
                ['app_id' => '777', 'rank' => 1, 'name' => 'Lucky', 'price' => 0, 'currency' => 'USD'],
            ],
        ], 200),
    ]);

    (new SyncChartSnapshotJob('ios', ChartCollection::TopFree->value, 'us', $category->id))
        ->handle(app(ITunesLookupConnector::class), app(GooglePlayConnector::class));

    expect(ChartSnapshot::count())->toBe(1)
        ->and(App::where('external_id', '777')->exists())->toBeTrue()
        ->and(ChartEntry::count())->toBe(1);
});

/*
|--------------------------------------------------------------------------
| ShouldBeUnique guard — duplicate dispatch for same key
|--------------------------------------------------------------------------
*/

it('produces identical uniqueIds for duplicate (platform, collection, country, category) tuples', function () {
    $a = new SyncChartSnapshotJob('ios', 'top_free', 'us', 1);
    $b = new SyncChartSnapshotJob('ios', 'top_free', 'us', 1);
    $c = new SyncChartSnapshotJob('ios', 'top_free', 'us', 2);

    expect($a->uniqueId())->toBe($b->uniqueId())
        ->and($a->uniqueId())->not->toBe($c->uniqueId());
});
