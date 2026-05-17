<?php

declare(strict_types=1);

use App\Enums\Platform;
use App\Jobs\Sync\SyncAppJob;
use App\Models\App;
use App\Models\SyncStatus;
use App\Services\AppSyncer;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    config()->set('appstorecat.connectors.appstore.base_url', 'http://scraper-ios.test');
    config()->set('appstorecat.connectors.appstore.timeout', 30);
    config()->set('appstorecat.connectors.gplay.base_url', 'http://scraper-android.test');
    config()->set('appstorecat.connectors.gplay.timeout', 30);
});

/*
|--------------------------------------------------------------------------
| Contract / Class metadata
|--------------------------------------------------------------------------
*/

it('implements ShouldQueue and ShouldBeUnique', function () {
    $job = new SyncAppJob(1);

    expect($job)->toBeInstanceOf(ShouldQueue::class)
        ->and($job)->toBeInstanceOf(ShouldBeUnique::class);
});

it('exposes the contract retry+backoff configuration', function () {
    $job = new SyncAppJob(1);

    expect($job->tries)->toBe(3)
        ->and($job->backoff)->toBe([30, 60, 120])
        ->and($job->uniqueFor)->toBe(3600)
        ->and($job->timeout)->toBe(600);
});

it('generates a per-app uniqueId', function () {
    $job1 = new SyncAppJob(42);
    $job2 = new SyncAppJob(42);
    $job3 = new SyncAppJob(99);

    expect($job1->uniqueId())->toBe('sync-app-42')
        ->and($job1->uniqueId())->toBe($job2->uniqueId())
        ->and($job1->uniqueId())->not->toBe($job3->uniqueId());
});

/*
|--------------------------------------------------------------------------
| Dispatch behaviour
|--------------------------------------------------------------------------
*/

it('dispatches on the platform-separated on-demand queue (iOS)', function () {
    Queue::fake();

    SyncAppJob::dispatch(1)->onQueue('sync-on-demand-ios');

    Queue::assertPushedOn('sync-on-demand-ios', SyncAppJob::class);
});

it('dispatches on the platform-separated tracked queue (Android)', function () {
    Queue::fake();

    SyncAppJob::dispatch(1)->onQueue('sync-tracked-android');

    Queue::assertPushedOn('sync-tracked-android', SyncAppJob::class);
});

/*
|--------------------------------------------------------------------------
| handle() — successful happy path (identity → listings → metrics → finalize)
|--------------------------------------------------------------------------
*/

it('drives status from QUEUED → PROCESSING (inside syncAll) and assigns a job_id', function () {
    $app = App::factory()->create();

    // Pre-create a QUEUED row so we can confirm SyncAppJob marks it processing
    // before invoking the syncer.
    SyncStatus::create([
        'app_id' => $app->id,
        'status' => SyncStatus::STATUS_QUEUED,
    ]);

    $syncer = Mockery::mock(AppSyncer::class);
    $syncer->shouldReceive('syncAll')
        ->once()
        ->andReturnUsing(function (App $a, SyncStatus $s) {
            // At this point SyncAppJob has populated job_id and the row exists.
            expect($s->job_id)->not->toBeNull();
            $s->forceFill([
                'status' => SyncStatus::STATUS_PROCESSING,
                'current_step' => SyncStatus::STEP_IDENTITY,
            ])->save();
        });

    (new SyncAppJob($app->id))->handle($syncer);

    $status = SyncStatus::where('app_id', $app->id)->first();
    expect($status->job_id)->not->toBeNull();
});

it('runs identity → finalize and marks the SyncStatus COMPLETED on the happy path', function () {
    $app = App::factory()->create([
        'platform' => Platform::Ios,
        'external_id' => '222',
        'origin_country_code' => 'us',
    ]);

    Http::fake([
        'http://scraper-ios.test/apps/222/identity*' => Http::response([
            'app_id' => '222',
            'name' => 'TwoTwoTwo',
            'price_model' => 'free',
            'version' => '1.0.0',
            'current_version_release_date' => '2026-01-01T00:00:00Z',
        ], 200),
        'http://scraper-ios.test/apps/222/listings*' => Http::response([
            'title' => 'TwoTwoTwo',
            'description' => 'desc',
        ], 200),
        'http://scraper-ios.test/apps/222/metrics*' => Http::response([
            'rating' => 4.7,
            'rating_count' => 1000,
            'file_size_bytes' => 12345,
        ], 200),
    ]);

    (new SyncAppJob($app->id))->handle(app(AppSyncer::class));

    $status = SyncStatus::where('app_id', $app->id)->first();

    expect($status)->not->toBeNull()
        ->and($status->status)->toBe(SyncStatus::STATUS_COMPLETED)
        ->and($status->current_step)->toBeNull()
        ->and($status->completed_at)->not->toBeNull()
        ->and($status->error_message)->toBeNull();

    expect($app->fresh()->last_synced_at)->not->toBeNull();
});

/*
|--------------------------------------------------------------------------
| handle() — failure paths
|--------------------------------------------------------------------------
*/

it('marks SyncStatus FAILED with error_message when identity fetch fails', function () {
    $app = App::factory()->create([
        'platform' => Platform::Ios,
        'external_id' => '404id',
        'origin_country_code' => 'us',
    ]);

    Http::fake([
        'http://scraper-ios.test/apps/404id/*' => Http::response(['error' => 'not found'], 404),
    ]);

    (new SyncAppJob($app->id))->handle(app(AppSyncer::class));

    $status = SyncStatus::where('app_id', $app->id)->first();

    expect($status)->not->toBeNull()
        ->and($status->status)->toBe(SyncStatus::STATUS_FAILED)
        ->and($status->error_message)->toContain('Identity fetch failed')
        ->and($status->completed_at)->not->toBeNull();
});

it('catches AppSyncer throwables, marks status FAILED, and rethrows', function () {
    $app = App::factory()->create();

    $syncer = Mockery::mock(AppSyncer::class);
    $syncer->shouldReceive('syncAll')
        ->once()
        ->andThrow(new RuntimeException('boom'));

    expect(fn () => (new SyncAppJob($app->id))->handle($syncer))
        ->toThrow(RuntimeException::class, 'boom');

    $status = SyncStatus::where('app_id', $app->id)->first();

    expect($status->status)->toBe(SyncStatus::STATUS_FAILED)
        ->and($status->error_message)->toBe('boom')
        ->and($status->completed_at)->not->toBeNull();
});

it('returns silently when the app does not exist', function () {
    $syncer = Mockery::mock(AppSyncer::class);
    $syncer->shouldNotReceive('syncAll');

    (new SyncAppJob(999_999))->handle($syncer);

    expect(SyncStatus::count())->toBe(0);
});

it('skips when another worker is already processing the same app', function () {
    $app = App::factory()->create();

    SyncStatus::create([
        'app_id' => $app->id,
        'status' => SyncStatus::STATUS_PROCESSING,
        'job_id' => 'other-worker-job-id',
    ]);

    $syncer = Mockery::mock(AppSyncer::class);
    $syncer->shouldNotReceive('syncAll');

    (new SyncAppJob($app->id))->handle($syncer);

    $status = SyncStatus::where('app_id', $app->id)->first();
    expect($status->job_id)->toBe('other-worker-job-id');
});

/*
|--------------------------------------------------------------------------
| ShouldBeUnique — duplicate dispatch guard via uniqueId
|--------------------------------------------------------------------------
*/

it('produces the same uniqueId across duplicate dispatch attempts for the same app', function () {
    $app = App::factory()->create();

    $first = new SyncAppJob($app->id);
    $second = new SyncAppJob($app->id);

    expect($first->uniqueId())->toBe("sync-app-{$app->id}")
        ->and($first->uniqueId())->toBe($second->uniqueId());
});
