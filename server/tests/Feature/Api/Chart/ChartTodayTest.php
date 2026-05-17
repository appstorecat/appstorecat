<?php

declare(strict_types=1);

use App\Enums\ChartCollection;
use App\Enums\Platform;
use App\Jobs\Chart\FetchChartSnapshotJob;
use App\Models\App;
use App\Models\ChartEntry;
use App\Models\ChartSnapshot;
use App\Models\StoreCategory;
use Illuminate\Support\Facades\Bus;

beforeEach(function () {
    createAuthenticatedUser();
    Bus::fake([FetchChartSnapshotJob::class]);
});

it('does NOT dispatch a fetch when today\'s snapshot is already present (isStale=false)', function () {
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

    $response = $this->getJson('/api/v1/charts?platform=ios&collection=top_free&country_code=us&category_id='.$category->id);

    $response->assertOk()
        ->assertJsonPath('meta.snapshot_date', now()->toDateString());

    Bus::assertNotDispatchedSync(FetchChartSnapshotJob::class);
});

it('dispatches a fresh fetch when the most recent snapshot is older than today (isStale=true)', function () {
    $category = StoreCategory::platform('ios')->whereNull('external_id')->where('type', 'app')->first();

    $app = App::factory()->create(['platform' => Platform::Ios]);
    // Snapshot from >24h ago — considered stale by the controller.
    $stale = ChartSnapshot::factory()->create([
        'platform' => Platform::Ios,
        'collection' => ChartCollection::TopFree,
        'country_code' => 'us',
        'category_id' => $category->id,
        'snapshot_date' => now()->subDays(2)->toDateString(),
    ]);
    ChartEntry::factory()->create([
        'trending_chart_id' => $stale->id,
        'app_id' => $app->id,
        'rank' => 1,
    ]);

    $response = $this->getJson('/api/v1/charts?platform=ios&collection=top_free&country_code=us&category_id='.$category->id);

    $response->assertOk();

    // Stale → controller MUST attempt to refresh.
    Bus::assertDispatchedSync(FetchChartSnapshotJob::class);

    // Because Bus::fake intercepts the dispatch, the stale snapshot is still
    // the most recent one and its entries are returned to the user.
    $response->assertJsonPath('meta.snapshot_date', now()->subDays(2)->toDateString())
        ->assertJsonCount(1, 'data');
});

it('also dispatches a fetch when no snapshot exists at all', function () {
    $category = StoreCategory::platform('ios')->whereNull('external_id')->where('type', 'app')->first();

    $this->getJson('/api/v1/charts?platform=ios&collection=top_free&country_code=us&category_id='.$category->id)
        ->assertOk()
        ->assertJsonPath('data', []);

    Bus::assertDispatchedSync(FetchChartSnapshotJob::class);
});
