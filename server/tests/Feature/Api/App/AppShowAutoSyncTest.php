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

it('dispatches a sync when last_synced_at is null', function () {
    $app = App::factory()->create([
        'platform' => Platform::Ios,
        'last_synced_at' => null,
    ]);

    $this->getJson("/api/v1/apps/ios/{$app->external_id}")
        ->assertOk();

    Queue::assertPushedOn('sync-on-demand-ios', SyncAppJob::class);

    expect(SyncStatus::where('app_id', $app->id)->exists())->toBeTrue();
});

it('dispatches a sync when last_synced_at is stale (older than refresh window)', function () {
    config()->set('appstorecat.sync.ios.tracked_app_refresh_hours', 24);

    $app = App::factory()->create([
        'platform' => Platform::Ios,
        'last_synced_at' => now()->subDays(2),
    ]);

    $this->getJson("/api/v1/apps/ios/{$app->external_id}")
        ->assertOk();

    Queue::assertPushedOn('sync-on-demand-ios', SyncAppJob::class);
});

it('does NOT dispatch a sync when last_synced_at is fresh', function () {
    config()->set('appstorecat.sync.ios.tracked_app_refresh_hours', 24);

    $app = App::factory()->create([
        'platform' => Platform::Ios,
        'last_synced_at' => now()->subMinutes(10),
    ]);

    $this->getJson("/api/v1/apps/ios/{$app->external_id}")
        ->assertOk();

    Queue::assertNotPushed(SyncAppJob::class);
});

it('does NOT dispatch a sync when an active sync is already PROCESSING', function () {
    $app = App::factory()->create([
        'last_synced_at' => null,
    ]);

    SyncStatus::create([
        'app_id' => $app->id,
        'status' => SyncStatus::STATUS_PROCESSING,
        'current_step' => SyncStatus::STEP_IDENTITY,
    ]);

    $this->getJson("/api/v1/apps/ios/{$app->external_id}")
        ->assertOk();

    Queue::assertNotPushed(SyncAppJob::class);
});

it('dispatches to the android queue for android apps with stale data', function () {
    config()->set('appstorecat.sync.android.tracked_app_refresh_hours', 24);

    $app = App::factory()->android()->create([
        'last_synced_at' => now()->subDays(2),
    ]);

    $this->getJson("/api/v1/apps/android/{$app->external_id}")
        ->assertOk();

    Queue::assertPushedOn('sync-on-demand-android', SyncAppJob::class);
});

it('respects a custom tracked_app_refresh_hours value when deciding staleness', function () {
    // 1-hour window, last sync 30 min ago → fresh, no dispatch.
    config()->set('appstorecat.sync.ios.tracked_app_refresh_hours', 1);

    $app = App::factory()->create([
        'last_synced_at' => now()->subMinutes(30),
    ]);

    $this->getJson("/api/v1/apps/ios/{$app->external_id}")
        ->assertOk();

    Queue::assertNotPushed(SyncAppJob::class);

    // 1-hour window, last sync 2 hours ago → stale, dispatch.
    $stale = App::factory()->create([
        'last_synced_at' => now()->subHours(2),
    ]);

    $this->getJson("/api/v1/apps/ios/{$stale->external_id}")
        ->assertOk();

    Queue::assertPushed(SyncAppJob::class);
});
