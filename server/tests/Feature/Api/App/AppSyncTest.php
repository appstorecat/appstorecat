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

it('dispatches SyncAppJob and returns SyncStatusResource for iOS app', function () {
    $app = App::factory()->create([
        'platform' => Platform::Ios,
        'external_id' => '389801252',
    ]);

    $response = $this->postJson("/api/v1/apps/ios/{$app->external_id}/sync");

    // Single-resource responses are not wrapped — `BaseResource::$wrap = null`
    // overrides the global `JsonResource::withoutWrapping()` so the payload sits
    // at the top level of the JSON body.
    $response->assertOk()
        ->assertJsonStructure([
            'app_id',
            'status',
            'current_step',
            'progress' => ['done', 'total'],
            'failed_items',
            'failed_items_count',
        ])
        ->assertJsonPath('app_id', $app->id)
        ->assertJsonPath('status', SyncStatus::STATUS_QUEUED);

    Queue::assertPushedOn('sync-on-demand-ios', SyncAppJob::class);
});

it('dispatches SyncAppJob on the android queue for an android app', function () {
    $app = App::factory()->android()->create([
        'external_id' => 'com.example.app',
    ]);

    $response = $this->postJson("/api/v1/apps/android/{$app->external_id}/sync");

    $response->assertOk()
        ->assertJsonPath('app_id', $app->id)
        ->assertJsonPath('status', SyncStatus::STATUS_QUEUED);

    Queue::assertPushedOn('sync-on-demand-android', SyncAppJob::class);
});

it('persists a SyncStatus row after dispatching sync', function () {
    $app = App::factory()->create();

    expect(SyncStatus::where('app_id', $app->id)->exists())->toBeFalse();

    $this->postJson("/api/v1/apps/ios/{$app->external_id}/sync")
        ->assertOk();

    $status = SyncStatus::where('app_id', $app->id)->first();
    expect($status)->not->toBeNull()
        ->and($status->status)->toBe(SyncStatus::STATUS_QUEUED);
});

it('does NOT dispatch a new SyncAppJob when status is PROCESSING', function () {
    $app = App::factory()->create();

    SyncStatus::create([
        'app_id' => $app->id,
        'status' => SyncStatus::STATUS_PROCESSING,
        'current_step' => SyncStatus::STEP_LISTINGS,
    ]);

    $response = $this->postJson("/api/v1/apps/ios/{$app->external_id}/sync");

    $response->assertOk()
        ->assertJsonPath('status', SyncStatus::STATUS_PROCESSING);

    Queue::assertNotPushed(SyncAppJob::class);
});

it('re-queues a sync after a previous COMPLETED run', function () {
    $app = App::factory()->create();

    SyncStatus::create([
        'app_id' => $app->id,
        'status' => SyncStatus::STATUS_COMPLETED,
        'progress_done' => 5,
        'progress_total' => 5,
        'completed_at' => now()->subHour(),
    ]);

    $response = $this->postJson("/api/v1/apps/ios/{$app->external_id}/sync");

    $response->assertOk()
        ->assertJsonPath('status', SyncStatus::STATUS_QUEUED)
        ->assertJsonPath('progress.done', 0)
        ->assertJsonPath('progress.total', 0);

    Queue::assertPushedOn('sync-on-demand-ios', SyncAppJob::class);
});

it('re-queues a sync after a previous FAILED run and clears error state', function () {
    $app = App::factory()->create();

    SyncStatus::create([
        'app_id' => $app->id,
        'status' => SyncStatus::STATUS_FAILED,
        'error_message' => 'previous failure',
    ]);

    $this->postJson("/api/v1/apps/ios/{$app->external_id}/sync")
        ->assertOk()
        ->assertJsonPath('status', SyncStatus::STATUS_QUEUED)
        ->assertJsonPath('error_message', null);

    Queue::assertPushed(SyncAppJob::class);
});

it('returns 404 when the app does not exist', function () {
    $this->postJson('/api/v1/apps/ios/9999999/sync')
        ->assertStatus(404);

    Queue::assertNotPushed(SyncAppJob::class);
});
