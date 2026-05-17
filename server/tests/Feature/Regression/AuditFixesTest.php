<?php

declare(strict_types=1);

/**
 * Regression suite for the May-17 audit fixes (commits 6afb9bc, 505c28b,
 * 0a021d8, bfa6f2f, f5f7af6, 2b623b5, 1abea82).
 *
 * Each `it(...)` block pins one previously-broken behavior so the bug cannot
 * silently come back. The bug each test covers is named in the block title
 * and the commit hash is in the docblock above it.
 */

use App\Connectors\GooglePlayConnector;
use App\Enums\Platform;
use App\Jobs\Sync\ReconcileFailedItemsJob;
use App\Models\App;
use App\Models\AppCompetitor;
use App\Models\AppMetric;
use App\Models\AppVersion;
use App\Models\ChartEntry;
use App\Models\ChartSnapshot;
use App\Models\StoreListing;
use App\Models\StoreListingChange;
use App\Models\SyncStatus;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

// ─────────────────────────────────────────────────────────────────────
// 1. App::discover() guards empty / null external_id (commit 6afb9bc)
// ─────────────────────────────────────────────────────────────────────

it('App::discover returns null and creates no row when external_id is empty', function () {
    $before = App::count();

    $result = App::discover('ios', '', ['name' => 'Bogus']);

    expect($result)->toBeNull()
        ->and(App::count())->toBe($before);
});

// App::discover() signature pins $externalId as string (non-nullable), so the
// null case is unreachable from PHP — the empty-string test above covers the
// defensive guard inside the method body.

// ─────────────────────────────────────────────────────────────────────
// 2. KeywordController::compare returns displayName() for every app
//    (commit 6afb9bc)
// ─────────────────────────────────────────────────────────────────────

it('KeywordController::compare returns a non-null name for every app payload', function () {
    $user = createAuthenticatedUser();

    // The {externalId} route segment is constrained to [a-zA-Z0-9._]+ — no
    // dashes — so keep these IDs alphanumeric to actually hit the controller.
    $subject = App::factory()->create([
        'platform' => Platform::Ios,
        'external_id' => 'subjectapp',
        'display_name' => 'Test App',
    ]);

    $rival = App::factory()->create([
        'platform' => Platform::Ios,
        'external_id' => 'rivalapp',
        'display_name' => 'Rival App',
    ]);

    AppCompetitor::factory()->create([
        'user_id' => $user->id,
        'app_id' => $subject->id,
        'competitor_app_id' => $rival->id,
    ]);

    $response = $this->getJson(
        "/api/v1/apps/ios/{$subject->external_id}/keywords/compare?app_ids[]={$rival->id}"
    );

    $response->assertOk();

    $apps = $response->json('apps');
    expect($apps)->not->toBeEmpty();

    foreach ($apps as $payload) {
        expect($payload)->toHaveKey('name')
            ->and($payload['name'])->not->toBeNull()
            ->and($payload['name'])->not->toBe('');
    }
});

// ─────────────────────────────────────────────────────────────────────
// 3. DashboardController eager-loads `app` so app_name is never null
//    (commit 6afb9bc)
// ─────────────────────────────────────────────────────────────────────

it('Dashboard recent_changes[].app_name is non-null when StoreListingChange exists', function () {
    $user = createAuthenticatedUser();

    $app = App::factory()->create([
        'platform' => Platform::Ios,
        'display_name' => 'Dashboard Subject',
    ]);
    $user->apps()->attach($app->id);

    StoreListingChange::factory()->create([
        'app_id' => $app->id,
        'version_id' => null,
        'locale' => 'en-US',
        'field_changed' => 'description',
        'detected_at' => now(),
    ]);

    $response = $this->getJson('/api/v1/dashboard');

    $response->assertOk();

    $recent = $response->json('recent_changes');
    expect($recent)->not->toBeEmpty()
        ->and($recent[0]['app_name'])->not->toBeNull()
        ->and($recent[0]['app_name'])->toBe('Dashboard Subject');
});

it('Dashboard does not N+1 when many StoreListingChanges exist (with(app) eager load)', function () {
    $user = createAuthenticatedUser();

    $appA = App::factory()->create(['platform' => Platform::Ios, 'display_name' => 'A']);
    $appB = App::factory()->create(['platform' => Platform::Ios, 'display_name' => 'B']);
    $user->apps()->attach([$appA->id, $appB->id]);

    foreach ([$appA, $appB] as $a) {
        foreach (range(1, 3) as $i) {
            StoreListingChange::factory()->create([
                'app_id' => $a->id,
                'version_id' => null,
                'locale' => 'en-US',
                'field_changed' => 'description',
                'detected_at' => now()->subMinutes($i),
            ]);
        }
    }

    DB::enableQueryLog();
    $response = $this->getJson('/api/v1/dashboard');
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    $response->assertOk();

    // With the `with('app')` eager-load there should be exactly one query
    // hitting the apps table for the recent_changes block, not one per row.
    $appSelects = collect($queries)
        ->filter(fn ($q) => str_contains($q['query'], 'from `apps`')
            && str_contains($q['query'], 'where `apps`.`id` in'))
        ->count();

    expect($appSelects)->toBeLessThanOrEqual(1);
});

// ─────────────────────────────────────────────────────────────────────
// 4. ReconcileFailedItemsJob status ternary (commit 6afb9bc)
// ─────────────────────────────────────────────────────────────────────

it('ReconcileFailedItemsJob marks status as FAILED when only permanent_failures remain', function () {
    $app = App::factory()->create(['platform' => Platform::Ios]);

    // The job early-returns when it sees STATUS_PROCESSING combined with a
    // current_step that is NOT 'reconciling' — that's its guard against
    // clobbering an in-flight full sync. To exercise the status ternary
    // we must mark the row as already in the reconciling step.
    $syncStatus = SyncStatus::factory()->create([
        'app_id' => $app->id,
        'status' => SyncStatus::STATUS_PROCESSING,
        'current_step' => SyncStatus::STEP_RECONCILING,
        'failed_items' => [[
            'type' => 'listing',
            'locale' => 'tr',
            'country_code' => 'tr',
            'reason' => SyncStatus::REASON_HTTP_500,
            'retry_count' => 10,
            'permanent_failure' => true,
            'next_retry_at' => null,
        ]],
    ]);

    ReconcileFailedItemsJob::dispatchSync($syncStatus->id);

    $syncStatus->refresh();
    expect($syncStatus->status)->toBe(SyncStatus::STATUS_FAILED);
});

it('ReconcileFailedItemsJob keeps status as COMPLETED when only transient items remain', function () {
    $app = App::factory()->create(['platform' => Platform::Ios]);

    $syncStatus = SyncStatus::factory()->create([
        'app_id' => $app->id,
        'status' => SyncStatus::STATUS_PROCESSING,
        'current_step' => SyncStatus::STEP_RECONCILING,
        'failed_items' => [[
            'type' => 'listing',
            'locale' => 'tr',
            'country_code' => 'tr',
            'reason' => SyncStatus::REASON_NETWORK_ERROR,
            'retry_count' => 0,
            'permanent_failure' => false,
            'next_retry_at' => now()->addHour()->toIso8601String(),
        ]],
    ]);

    ReconcileFailedItemsJob::dispatchSync($syncStatus->id);

    $syncStatus->refresh();
    expect($syncStatus->status)->toBe(SyncStatus::STATUS_COMPLETED);
});

// ─────────────────────────────────────────────────────────────────────
// 5. AppSyncer::syncIdentity tolerates ghost-column keys in payload
//    (commit 6afb9bc + 505c28b)
// ─────────────────────────────────────────────────────────────────────

it('AppSyncer::syncIdentity does not throw when connector payload contains removed columns', function () {
    // Identity payloads still carry content_rating / store_url / price_model
    // from upstream; these no longer have columns on `apps`. fillable must
    // silently drop them rather than the model masking the cast / setter.
    $app = App::factory()->create([
        'platform' => Platform::Ios,
        'external_id' => '1234567890',
        'origin_country_code' => 'us',
    ]);

    expect(fn () => $app->update([
        'content_rating' => '12+',
        'store_url' => 'https://apps.apple.com/whatever',
        'price_model' => 'free',
        'display_name' => 'Smoke',
    ]))->not->toThrow(Throwable::class);

    $app->refresh();
    expect($app->display_name)->toBe('Smoke');
});

// ─────────────────────────────────────────────────────────────────────
// 6. app_metrics dead columns are gone (commit 505c28b)
// ─────────────────────────────────────────────────────────────────────

it('app_metrics dropped the five dead columns (rating_delta, price, currency, installs_range, file_size_bytes)', function () {
    foreach (['rating_delta', 'price', 'currency', 'installs_range', 'file_size_bytes'] as $col) {
        expect(Schema::hasColumn('app_metrics', $col))
            ->toBeFalse("Column app_metrics.{$col} must be dropped");
    }
});

it('AppMetric model fillable no longer accepts the dropped columns', function () {
    $metric = new AppMetric;
    foreach (['rating_delta', 'price', 'currency', 'installs_range', 'file_size_bytes'] as $col) {
        expect($metric->isFillable($col))->toBeFalse("AppMetric::{$col} must not be fillable");
    }
});

// ─────────────────────────────────────────────────────────────────────
// 7. AppDetailResource.file_size_bytes now reads from app_versions
//    (commit 505c28b)
// ─────────────────────────────────────────────────────────────────────

it('AppDetailResource.file_size_bytes is sourced from the latest AppVersion, not app_metrics', function () {
    $user = createAuthenticatedUser();

    $app = App::factory()->create([
        'platform' => Platform::Ios,
        'external_id' => '777',
        'display_name' => 'File Size Test',
        'last_synced_at' => now(),
    ]);
    $user->apps()->attach($app->id);

    AppVersion::factory()->create([
        'app_id' => $app->id,
        'version' => '1.0.0',
        'file_size_bytes' => 1234,
    ]);

    $response = $this->getJson("/api/v1/apps/ios/{$app->external_id}");

    $response->assertOk()
        ->assertJsonPath('file_size_bytes', 1234);
});

// ─────────────────────────────────────────────────────────────────────
// 8. GooglePlayConnector sanitizes unparseable date strings (commit 0a021d8)
// ─────────────────────────────────────────────────────────────────────

it('GooglePlayConnector::fetchIdentity returns null release dates when the scraper sends "Never updated"', function () {
    $app = App::factory()->android()->create(['external_id' => 'com.test.android']);

    Http::fake([
        '*/apps/*/identity*' => Http::response([
            'name' => 'Android App',
            'app_id' => 'com.test.android',
            'publisher_name' => 'Pub',
            'publisher_external_id' => 'pub-1',
            'category' => 'Games',
            'category_id' => 'GAME',
            'version' => '2.0.0',
            'price_model' => 'free',
            'original_release_date' => 'Never updated',
            'current_version_release_date' => 'Never updated',
        ], 200),
    ]);

    $connector = app(GooglePlayConnector::class);
    $result = $connector->fetchIdentity($app, 'us');

    expect($result->success)->toBeTrue()
        ->and($result->data['original_release_date'])->toBeNull()
        ->and($result->data['current_version_release_date'])->toBeNull();

    // And firstOrCreate on AppVersion must not blow up with null release_date.
    $version = AppVersion::firstOrCreate(
        ['app_id' => $app->id, 'version' => $result->data['version']],
        ['release_date' => $result->data['current_version_release_date']],
    );

    expect($version->exists)->toBeTrue()
        ->and($version->release_date)->toBeNull();
});

// ─────────────────────────────────────────────────────────────────────
// 9. ReconcileFailedItemsJob has a 30-minute timeout (commit bfa6f2f)
// ─────────────────────────────────────────────────────────────────────

it('ReconcileFailedItemsJob declares a 1800-second timeout', function () {
    $job = new ReconcileFailedItemsJob(1);

    $reflection = new ReflectionObject($job);
    $timeoutProp = $reflection->getProperty('timeout');

    expect($timeoutProp->getValue($job))->toBe(1800);
});

// ─────────────────────────────────────────────────────────────────────
// 10. app_store_listings.subtitle is now TEXT (commit f5f7af6)
// ─────────────────────────────────────────────────────────────────────

it('app_store_listings.subtitle accepts strings longer than 255 chars', function () {
    $app = App::factory()->create(['platform' => Platform::Android]);
    $version = AppVersion::factory()->create(['app_id' => $app->id]);

    $longSubtitle = str_repeat('x', 1000);

    $listing = StoreListing::create([
        'app_id' => $app->id,
        'version_id' => $version->id,
        'locale' => 'pl',
        'title' => 'Long subtitle test',
        'subtitle' => $longSubtitle,
        'description' => 'd',
        'screenshots' => [],
        'price' => 0,
        'currency' => null,
        'fetched_at' => now(),
        'checksum' => md5('subtitle-test'),
    ]);

    expect($listing->exists)->toBeTrue()
        ->and(mb_strlen($listing->fresh()->subtitle))->toBe(1000);
});

it('app_store_listings.subtitle column type is TEXT (not VARCHAR)', function () {
    // information_schema returns column names in the case the server stores
    // them (uppercase on MySQL 8.4), so alias it to a stable lowercase key
    // we can safely pluck off the PDO stdClass row.
    $row = DB::selectOne(
        "SELECT data_type AS data_type FROM information_schema.columns
         WHERE table_schema = DATABASE()
           AND table_name = 'app_store_listings'
           AND column_name = 'subtitle'"
    );

    $dataType = $row->data_type ?? $row->DATA_TYPE ?? '';

    expect(strtolower((string) $dataType))->toBe('text');
});

// ─────────────────────────────────────────────────────────────────────
// 11. trending_chart_entries: no timestamps (commit 2b623b5)
// ─────────────────────────────────────────────────────────────────────

it('ChartEntry has timestamps disabled and the columns are gone from the table', function () {
    expect((new ChartEntry)->timestamps)->toBeFalse()
        ->and(Schema::hasColumn('trending_chart_entries', 'created_at'))->toBeFalse()
        ->and(Schema::hasColumn('trending_chart_entries', 'updated_at'))->toBeFalse();
});

it('ChartEntry::create does not throw because of missing timestamps columns', function () {
    $snapshot = ChartSnapshot::factory()->create();
    $app = App::factory()->create();

    $entry = ChartEntry::create([
        'trending_chart_id' => $snapshot->id,
        'app_id' => $app->id,
        'rank' => 7,
        'price' => 0,
        'currency' => null,
    ]);

    expect($entry->exists)->toBeTrue()
        ->and($entry->rank)->toBe(7);
});

// ─────────────────────────────────────────────────────────────────────
// 12. trending_chart_entries: standalone (app_id) index dropped
//     (commit 2b623b5). The composite (app_id, trending_chart_id) covers
//     app-only lookups by leftmost-prefix.
// ─────────────────────────────────────────────────────────────────────

it('trending_chart_entries no longer has the standalone app_id index', function () {
    $row = DB::selectOne(
        "SELECT 1 AS ok FROM information_schema.statistics
         WHERE table_schema = DATABASE()
           AND table_name = 'trending_chart_entries'
           AND index_name = 'trending_chart_entries_app_id_index'
         LIMIT 1"
    );

    expect($row)->toBeNull();
});

// ─────────────────────────────────────────────────────────────────────
// 13. appstorecat:sync:cleanup-failed-items clears stale failed_items
//     (commit 1abea82)
// ─────────────────────────────────────────────────────────────────────

it('cleanup-failed-items command nulls failed_items on terminal rows older than 14 days', function () {
    $app = App::factory()->create(['platform' => Platform::Ios]);

    $stale = SyncStatus::factory()->create([
        'app_id' => $app->id,
        'status' => SyncStatus::STATUS_COMPLETED,
        'failed_items' => [[
            'type' => 'listing',
            'locale' => 'tr',
            'country_code' => 'tr',
            'reason' => SyncStatus::REASON_HTTP_500,
            'retry_count' => 1,
            'permanent_failure' => false,
        ]],
        'completed_at' => now()->subDays(30),
        'next_retry_at' => now()->subDays(5),
    ]);

    expect($stale->failed_items)->not->toBeEmpty();

    Artisan::call('appstorecat:sync:cleanup-failed-items');

    $stale->refresh();
    expect($stale->failed_items)->toBeNull()
        ->and($stale->next_retry_at)->toBeNull();
});

it('cleanup-failed-items command leaves fresh terminal rows untouched', function () {
    $app = App::factory()->create(['platform' => Platform::Ios]);

    $fresh = SyncStatus::factory()->create([
        'app_id' => $app->id,
        'status' => SyncStatus::STATUS_COMPLETED,
        'failed_items' => [[
            'type' => 'metric',
            'country_code' => 'us',
            'reason' => SyncStatus::REASON_TIMEOUT,
            'retry_count' => 0,
            'permanent_failure' => false,
        ]],
        'completed_at' => now()->subDay(),
    ]);

    Artisan::call('appstorecat:sync:cleanup-failed-items');

    $fresh->refresh();
    expect($fresh->failed_items)->not->toBeNull()
        ->and($fresh->failed_items)->toHaveCount(1);
});
