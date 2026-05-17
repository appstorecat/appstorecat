<?php

declare(strict_types=1);

use App\Jobs\Sync\ReconcileFailedItemsJob;
use App\Models\App;
use App\Models\SyncStatus;
use App\Services\AppSyncer;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;

/*
|--------------------------------------------------------------------------
| Contract / Class metadata (regression — commit bfa6f2f)
|--------------------------------------------------------------------------
*/

it('implements ShouldQueue and ShouldBeUnique', function () {
    $job = new ReconcileFailedItemsJob(1);

    expect($job)->toBeInstanceOf(ShouldQueue::class)
        ->and($job)->toBeInstanceOf(ShouldBeUnique::class);
});

it('has $tries=1 and the regression $timeout=1800', function () {
    $job = new ReconcileFailedItemsJob(1);

    // Regression — commit bfa6f2f: long reconcile runs must not be killed by
    // the default 60s worker timeout.
    expect($job->tries)->toBe(1)
        ->and($job->timeout)->toBe(1800)
        ->and($job->uniqueFor)->toBe(1800);
});

it('produces a per-sync-status uniqueId', function () {
    $job = new ReconcileFailedItemsJob(42);

    expect($job->uniqueId())->toBe('reconcile-sync-status-42');
});

/*
|--------------------------------------------------------------------------
| handle() — successful retry path
|--------------------------------------------------------------------------
*/

it('retries transient items via AppSyncer and drops successful ones from failed_items', function () {
    $app = App::factory()->create();

    $failing = [
        'type' => 'listing',
        'locale' => 'en-US',
        'country_code' => 'us',
        'reason' => SyncStatus::REASON_HTTP_500,
        'retry_count' => 1,
        'permanent_failure' => false,
        'next_retry_at' => now()->subMinute()->toIso8601String(),
    ];

    $status = SyncStatus::create([
        'app_id' => $app->id,
        'status' => SyncStatus::STATUS_COMPLETED,
        'failed_items' => [$failing],
    ]);

    $syncer = Mockery::mock(AppSyncer::class);
    $syncer->shouldReceive('retryFailedItem')
        ->once()
        ->with(Mockery::on(fn ($a) => $a->id === $app->id), Mockery::on(fn ($i) => $i['locale'] === 'en-US'))
        ->andReturn(true);

    (new ReconcileFailedItemsJob($status->id))->handle($syncer);

    $fresh = $status->fresh();
    expect($fresh->failed_items)->toBe([])
        ->and($fresh->status)->toBe(SyncStatus::STATUS_COMPLETED)
        ->and($fresh->current_step)->toBeNull();
});

it('skips items whose next_retry_at is still in the future', function () {
    $app = App::factory()->create();

    $futureItem = [
        'type' => 'metric',
        'country_code' => 'us',
        'reason' => SyncStatus::REASON_HTTP_429,
        'retry_count' => 1,
        'permanent_failure' => false,
        'next_retry_at' => now()->addHour()->toIso8601String(),
    ];

    $status = SyncStatus::create([
        'app_id' => $app->id,
        'status' => SyncStatus::STATUS_COMPLETED,
        'failed_items' => [$futureItem],
    ]);

    $syncer = Mockery::mock(AppSyncer::class);
    $syncer->shouldNotReceive('retryFailedItem');

    (new ReconcileFailedItemsJob($status->id))->handle($syncer);

    $fresh = $status->fresh();
    expect($fresh->failed_items)->toHaveCount(1)
        ->and($fresh->failed_items[0]['country_code'])->toBe('us')
        ->and($fresh->current_step)->toBeNull();
});

it('skips items marked permanent_failure without invoking AppSyncer', function () {
    $app = App::factory()->create();

    $permanent = [
        'type' => 'listing',
        'locale' => 'en-US',
        'country_code' => 'us',
        'reason' => SyncStatus::REASON_EMPTY_RESPONSE,
        'retry_count' => 99,
        'permanent_failure' => true,
        'next_retry_at' => null,
    ];

    $status = SyncStatus::create([
        'app_id' => $app->id,
        'status' => SyncStatus::STATUS_COMPLETED,
        'failed_items' => [$permanent],
    ]);

    $syncer = Mockery::mock(AppSyncer::class);
    $syncer->shouldNotReceive('retryFailedItem');

    (new ReconcileFailedItemsJob($status->id))->handle($syncer);

    $fresh = $status->fresh();
    expect($fresh->failed_items)->toHaveCount(1)
        ->and($fresh->failed_items[0]['permanent_failure'])->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Permanent failure transition — retry_count >= maxForReason
|--------------------------------------------------------------------------
*/

it('flags an item as permanent_failure once retry_count reaches maxForReason', function () {
    config()->set('appstorecat.sync.item_retry.max_attempts', [
        SyncStatus::REASON_EMPTY_RESPONSE => 3,
    ]);

    $app = App::factory()->create();

    $item = [
        'type' => 'listing',
        'locale' => 'en-US',
        'country_code' => 'us',
        'reason' => SyncStatus::REASON_EMPTY_RESPONSE,
        'retry_count' => 2,
        'permanent_failure' => false,
        'next_retry_at' => now()->subMinute()->toIso8601String(),
    ];

    $status = SyncStatus::create([
        'app_id' => $app->id,
        'status' => SyncStatus::STATUS_COMPLETED,
        'failed_items' => [$item],
    ]);

    $syncer = Mockery::mock(AppSyncer::class);
    $syncer->shouldReceive('retryFailedItem')->once()->andReturn(false);

    (new ReconcileFailedItemsJob($status->id))->handle($syncer);

    $fresh = $status->fresh();
    expect($fresh->failed_items)->toHaveCount(1)
        ->and($fresh->failed_items[0]['retry_count'])->toBe(3)
        ->and($fresh->failed_items[0]['permanent_failure'])->toBeTrue()
        ->and($fresh->failed_items[0]['next_retry_at'])->toBeNull();
});

/*
|--------------------------------------------------------------------------
| Audit ternary regression (commit f5f7af6) — all permanent → status=FAILED
|--------------------------------------------------------------------------
*/

it('sets status=FAILED when all remaining items are permanent_failure (audit bug fix)', function () {
    config()->set('appstorecat.sync.item_retry.max_attempts', [
        SyncStatus::REASON_EMPTY_RESPONSE => 1,
    ]);

    $app = App::factory()->create();

    // A single item that will transition to permanent_failure on this run.
    $item = [
        'type' => 'listing',
        'locale' => 'en-US',
        'country_code' => 'us',
        'reason' => SyncStatus::REASON_EMPTY_RESPONSE,
        'retry_count' => 0,
        'permanent_failure' => false,
        'next_retry_at' => now()->subMinute()->toIso8601String(),
    ];

    $status = SyncStatus::create([
        'app_id' => $app->id,
        'status' => SyncStatus::STATUS_COMPLETED,
        'failed_items' => [$item],
    ]);

    $syncer = Mockery::mock(AppSyncer::class);
    $syncer->shouldReceive('retryFailedItem')->once()->andReturn(false);

    (new ReconcileFailedItemsJob($status->id))->handle($syncer);

    $fresh = $status->fresh();
    expect($fresh->status)->toBe(SyncStatus::STATUS_FAILED)
        ->and($fresh->failed_items)->toHaveCount(1)
        ->and($fresh->failed_items[0]['permanent_failure'])->toBeTrue();
});

it('keeps status=COMPLETED when at least one transient item remains', function () {
    config()->set('appstorecat.sync.item_retry.max_attempts', [
        SyncStatus::REASON_EMPTY_RESPONSE => 1,
        SyncStatus::REASON_HTTP_500 => 10,
    ]);

    $app = App::factory()->create();

    $permanentNow = [
        'type' => 'listing',
        'locale' => 'en-US',
        'country_code' => 'us',
        'reason' => SyncStatus::REASON_EMPTY_RESPONSE,
        'retry_count' => 0,
        'permanent_failure' => false,
        'next_retry_at' => now()->subMinute()->toIso8601String(),
    ];

    $stillTransient = [
        'type' => 'metric',
        'country_code' => 'tr',
        'reason' => SyncStatus::REASON_HTTP_500,
        'retry_count' => 0,
        'permanent_failure' => false,
        'next_retry_at' => now()->subMinute()->toIso8601String(),
    ];

    $status = SyncStatus::create([
        'app_id' => $app->id,
        'status' => SyncStatus::STATUS_COMPLETED,
        'failed_items' => [$permanentNow, $stillTransient],
    ]);

    $syncer = Mockery::mock(AppSyncer::class);
    $syncer->shouldReceive('retryFailedItem')->twice()->andReturn(false);

    (new ReconcileFailedItemsJob($status->id))->handle($syncer);

    $fresh = $status->fresh();
    expect($fresh->status)->toBe(SyncStatus::STATUS_COMPLETED)
        ->and($fresh->failed_items)->toHaveCount(2);
});

/*
|--------------------------------------------------------------------------
| Guards
|--------------------------------------------------------------------------
*/

it('is a no-op when failed_items is empty (current_step stays null)', function () {
    $app = App::factory()->create();
    $status = SyncStatus::create([
        'app_id' => $app->id,
        'status' => SyncStatus::STATUS_COMPLETED,
        'failed_items' => [],
    ]);

    $syncer = Mockery::mock(AppSyncer::class);
    $syncer->shouldNotReceive('retryFailedItem');

    (new ReconcileFailedItemsJob($status->id))->handle($syncer);

    $fresh = $status->fresh();
    expect($fresh->current_step)->toBeNull()
        ->and($fresh->status)->toBe(SyncStatus::STATUS_COMPLETED);
});

it('skips when sync_status row is missing', function () {
    $syncer = Mockery::mock(AppSyncer::class);
    $syncer->shouldNotReceive('retryFailedItem');

    expect(fn () => (new ReconcileFailedItemsJob(9_999_999))->handle($syncer))
        ->not->toThrow(Throwable::class);
});

it('skips when the app is currently being full-synced (PROCESSING & not reconciling)', function () {
    $app = App::factory()->create();
    $status = SyncStatus::create([
        'app_id' => $app->id,
        'status' => SyncStatus::STATUS_PROCESSING,
        'current_step' => SyncStatus::STEP_LISTINGS,
        'failed_items' => [[
            'type' => 'listing',
            'locale' => 'en-US',
            'country_code' => 'us',
            'reason' => SyncStatus::REASON_HTTP_500,
            'retry_count' => 0,
            'permanent_failure' => false,
            'next_retry_at' => now()->subMinute()->toIso8601String(),
        ]],
    ]);

    $syncer = Mockery::mock(AppSyncer::class);
    $syncer->shouldNotReceive('retryFailedItem');

    (new ReconcileFailedItemsJob($status->id))->handle($syncer);

    expect($status->fresh()->current_step)->toBe(SyncStatus::STEP_LISTINGS);
});
