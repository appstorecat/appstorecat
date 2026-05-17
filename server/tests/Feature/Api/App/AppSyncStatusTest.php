<?php

declare(strict_types=1);

use App\Enums\Platform;
use App\Jobs\Sync\SyncAppJob;
use App\Models\App;
use App\Models\SyncStatus;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
    createAuthenticatedUser();
});

it('returns SyncStatusResource with queued status when no prior sync exists', function () {
    $app = App::factory()->create([
        'platform' => Platform::Ios,
        'external_id' => '389801252',
    ]);

    expect(SyncStatus::where('app_id', $app->id)->exists())->toBeFalse();

    $response = $this->getJson("/api/v1/apps/ios/{$app->external_id}/sync-status");

    // The controller uses `firstOrCreate`, so the first GET creates the row and
    // Laravel sets the response status to 201 (wasRecentlyCreated). Single-resource
    // responses are not wrapped — `BaseResource::$wrap = null` overrides the global
    // `JsonResource::withoutWrapping()` and returns the payload at the top level.
    $response->assertStatus(201)
        ->assertJsonStructure([
            'app_id',
            'status',
            'current_step',
            'progress' => ['done', 'total'],
            'failed_items',
            'failed_items_count',
            'error_message',
            'started_at',
            'completed_at',
        ])
        ->assertJsonPath('app_id', $app->id)
        ->assertJsonPath('status', SyncStatus::STATUS_QUEUED)
        ->assertJsonPath('current_step', null)
        // `firstOrCreate` returns the in-memory instance that was just created with
        // only app_id + status. The `progress_done` / `progress_total` columns rely
        // on the DB default (0) at insert time, so the un-refreshed model returns
        // null here. The resource forwards that through unchanged.
        ->assertJsonPath('progress.done', null)
        ->assertJsonPath('progress.total', null)
        ->assertJsonPath('failed_items_count', 0);

    // sync-status endpoint must NOT dispatch a job — it's a read.
    Queue::assertNotPushed(SyncAppJob::class);

    // firstOrCreate persisted the row.
    expect(SyncStatus::where('app_id', $app->id)->exists())->toBeTrue();
});

it('returns existing sync status fields when sync is in progress', function () {
    $app = App::factory()->create();

    SyncStatus::create([
        'app_id' => $app->id,
        'status' => SyncStatus::STATUS_PROCESSING,
        'current_step' => SyncStatus::STEP_LISTINGS,
        'progress_done' => 3,
        'progress_total' => 10,
        'started_at' => now()->subSeconds(30),
    ]);

    $response = $this->getJson("/api/v1/apps/ios/{$app->external_id}/sync-status");

    $response->assertOk()
        ->assertJsonPath('status', SyncStatus::STATUS_PROCESSING)
        ->assertJsonPath('current_step', SyncStatus::STEP_LISTINGS)
        ->assertJsonPath('progress.done', 3)
        ->assertJsonPath('progress.total', 10);

    Queue::assertNotPushed(SyncAppJob::class);
});

it('exposes failed_items and failed_items_count', function () {
    $app = App::factory()->create();

    SyncStatus::create([
        'app_id' => $app->id,
        'status' => SyncStatus::STATUS_COMPLETED,
        'progress_done' => 5,
        'progress_total' => 5,
        'failed_items' => [
            ['type' => 'listing', 'locale' => 'tr', 'reason' => SyncStatus::REASON_HTTP_500, 'retry_count' => 1, 'permanent_failure' => false],
            ['type' => 'metric', 'country_code' => 'us', 'reason' => SyncStatus::REASON_TIMEOUT, 'retry_count' => 2, 'permanent_failure' => false],
        ],
        'completed_at' => now(),
    ]);

    $this->getJson("/api/v1/apps/ios/{$app->external_id}/sync-status")
        ->assertOk()
        ->assertJsonPath('status', SyncStatus::STATUS_COMPLETED)
        ->assertJsonPath('failed_items_count', 2)
        ->assertJsonPath('failed_items.0.type', 'listing')
        ->assertJsonPath('failed_items.1.type', 'metric');
});

it('exposes error_message on failed sync', function () {
    $app = App::factory()->create();

    SyncStatus::create([
        'app_id' => $app->id,
        'status' => SyncStatus::STATUS_FAILED,
        'error_message' => 'scraper unavailable',
    ]);

    $this->getJson("/api/v1/apps/ios/{$app->external_id}/sync-status")
        ->assertOk()
        ->assertJsonPath('status', SyncStatus::STATUS_FAILED)
        ->assertJsonPath('error_message', 'scraper unavailable');
});

it('returns 404 when the app does not exist', function () {
    $this->getJson('/api/v1/apps/ios/9999999/sync-status')
        ->assertStatus(404);
});
