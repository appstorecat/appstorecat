<?php

declare(strict_types=1);

use App\Enums\ChartCollection;
use App\Enums\Platform;
use App\Models\App;
use App\Models\ChartEntry;
use App\Models\ChartSnapshot;
use App\Models\StoreCategory;

beforeEach(function () {
    createAuthenticatedUser();
    $this->iosCategory = StoreCategory::where('platform', Platform::Ios->value)->first();
});

it('returns ranking rows for the requested date and collection', function () {
    $app = App::factory()->create(['platform' => Platform::Ios, 'external_id' => '777']);

    $snapshot = ChartSnapshot::factory()->create([
        'platform' => Platform::Ios,
        'collection' => ChartCollection::TopFree,
        'country_code' => 'us',
        'category_id' => $this->iosCategory->id,
        'snapshot_date' => '2026-05-15',
    ]);
    ChartEntry::factory()->create([
        'trending_chart_id' => $snapshot->id,
        'app_id' => $app->id,
        'rank' => 4,
    ]);

    $response = $this->getJson("/api/v1/apps/ios/{$app->external_id}/rankings?collection=top_free&date=2026-05-15");

    $response->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.rank', 4)
        ->assertJsonPath('0.collection', 'top_free')
        ->assertJsonPath('0.country_code', 'us')
        ->assertJsonPath('0.snapshot_date', '2026-05-15');
});

it('filters out other collections when a specific collection is requested', function () {
    $app = App::factory()->create(['platform' => Platform::Ios, 'external_id' => '778']);

    $topFree = ChartSnapshot::factory()->create([
        'platform' => Platform::Ios,
        'collection' => ChartCollection::TopFree,
        'country_code' => 'us',
        'category_id' => $this->iosCategory->id,
        'snapshot_date' => '2026-05-15',
    ]);
    $topPaid = ChartSnapshot::factory()->create([
        'platform' => Platform::Ios,
        'collection' => ChartCollection::TopPaid,
        'country_code' => 'us',
        'category_id' => $this->iosCategory->id,
        'snapshot_date' => '2026-05-15',
    ]);
    ChartEntry::factory()->create(['trending_chart_id' => $topFree->id, 'app_id' => $app->id, 'rank' => 1]);
    ChartEntry::factory()->create(['trending_chart_id' => $topPaid->id, 'app_id' => $app->id, 'rank' => 9]);

    $response = $this->getJson("/api/v1/apps/ios/{$app->external_id}/rankings?collection=top_free&date=2026-05-15");

    $response->assertOk()->assertJsonCount(1);
    expect($response->json('0.collection'))->toBe('top_free');
});

it('returns entries from all collections when collection=all is passed', function () {
    $app = App::factory()->create(['platform' => Platform::Ios, 'external_id' => '779']);

    $topFree = ChartSnapshot::factory()->create([
        'platform' => Platform::Ios, 'collection' => ChartCollection::TopFree,
        'country_code' => 'us', 'category_id' => $this->iosCategory->id, 'snapshot_date' => '2026-05-15',
    ]);
    $topGrossing = ChartSnapshot::factory()->create([
        'platform' => Platform::Ios, 'collection' => ChartCollection::TopGrossing,
        'country_code' => 'us', 'category_id' => $this->iosCategory->id, 'snapshot_date' => '2026-05-15',
    ]);
    ChartEntry::factory()->create(['trending_chart_id' => $topFree->id, 'app_id' => $app->id, 'rank' => 1]);
    ChartEntry::factory()->create(['trending_chart_id' => $topGrossing->id, 'app_id' => $app->id, 'rank' => 7]);

    $response = $this->getJson("/api/v1/apps/ios/{$app->external_id}/rankings?collection=all&date=2026-05-15");

    $response->assertOk()->assertJsonCount(2);
});

it('returns an empty list when no chart entry exists for the date', function () {
    $app = App::factory()->create(['platform' => Platform::Ios, 'external_id' => '780']);

    $response = $this->getJson("/api/v1/apps/ios/{$app->external_id}/rankings?date=2026-05-15");

    $response->assertOk()->assertJsonCount(0);
});

it('computes previous_rank, rank_change and status when an earlier snapshot exists', function () {
    $app = App::factory()->create(['platform' => Platform::Ios, 'external_id' => '781']);

    $previous = ChartSnapshot::factory()->create([
        'platform' => Platform::Ios, 'collection' => ChartCollection::TopFree,
        'country_code' => 'us', 'category_id' => $this->iosCategory->id, 'snapshot_date' => '2026-05-14',
    ]);
    $latest = ChartSnapshot::factory()->create([
        'platform' => Platform::Ios, 'collection' => ChartCollection::TopFree,
        'country_code' => 'us', 'category_id' => $this->iosCategory->id, 'snapshot_date' => '2026-05-15',
    ]);

    ChartEntry::factory()->create(['trending_chart_id' => $previous->id, 'app_id' => $app->id, 'rank' => 10]);
    ChartEntry::factory()->create(['trending_chart_id' => $latest->id, 'app_id' => $app->id, 'rank' => 3]);

    $response = $this->getJson("/api/v1/apps/ios/{$app->external_id}/rankings?date=2026-05-15");

    $response->assertOk()
        ->assertJsonPath('0.rank', 3)
        ->assertJsonPath('0.previous_rank', 10)
        ->assertJsonPath('0.rank_change', 7)
        ->assertJsonPath('0.status', 'up');
});

it('marks the entry as "new" when there is no previous snapshot', function () {
    $app = App::factory()->create(['platform' => Platform::Ios, 'external_id' => '782']);

    $latest = ChartSnapshot::factory()->create([
        'platform' => Platform::Ios, 'collection' => ChartCollection::TopFree,
        'country_code' => 'us', 'category_id' => $this->iosCategory->id, 'snapshot_date' => '2026-05-15',
    ]);
    ChartEntry::factory()->create(['trending_chart_id' => $latest->id, 'app_id' => $app->id, 'rank' => 5]);

    $response = $this->getJson("/api/v1/apps/ios/{$app->external_id}/rankings?date=2026-05-15");

    $response->assertOk()
        ->assertJsonPath('0.previous_rank', null)
        ->assertJsonPath('0.rank_change', null)
        ->assertJsonPath('0.status', 'new');
});

it('marks status as "down" when current rank is worse than previous', function () {
    $app = App::factory()->create(['platform' => Platform::Ios, 'external_id' => '783']);

    $previous = ChartSnapshot::factory()->create([
        'platform' => Platform::Ios, 'collection' => ChartCollection::TopFree,
        'country_code' => 'us', 'category_id' => $this->iosCategory->id, 'snapshot_date' => '2026-05-14',
    ]);
    $latest = ChartSnapshot::factory()->create([
        'platform' => Platform::Ios, 'collection' => ChartCollection::TopFree,
        'country_code' => 'us', 'category_id' => $this->iosCategory->id, 'snapshot_date' => '2026-05-15',
    ]);
    ChartEntry::factory()->create(['trending_chart_id' => $previous->id, 'app_id' => $app->id, 'rank' => 3]);
    ChartEntry::factory()->create(['trending_chart_id' => $latest->id, 'app_id' => $app->id, 'rank' => 9]);

    $response = $this->getJson("/api/v1/apps/ios/{$app->external_id}/rankings?date=2026-05-15");

    $response->assertOk()
        ->assertJsonPath('0.status', 'down')
        ->assertJsonPath('0.rank_change', -6);
});

it('returns 404 when ranking endpoint is hit with an unknown app', function () {
    $this->getJson('/api/v1/apps/ios/not-a-real-app/rankings?date=2026-05-15')
        ->assertStatus(404);
});

it('rejects an invalid date format on the rankings endpoint', function () {
    $app = App::factory()->create(['platform' => Platform::Ios]);

    $this->getJson("/api/v1/apps/ios/{$app->external_id}/rankings?date=2026/05/15")
        ->assertStatus(422);
});
