<?php

declare(strict_types=1);

namespace App\Jobs\Sync;

use App\Models\App;
use App\Models\SyncStatus;
use App\Services\AppSyncer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class ReconcileFailedItemsJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $uniqueFor = 1800;

    public function __construct(
        private readonly int $syncStatusId,
    ) {}

    public function uniqueId(): string
    {
        return "reconcile-sync-status-{$this->syncStatusId}";
    }

    public function handle(AppSyncer $syncer): void
    {
        $syncStatus = SyncStatus::find($this->syncStatusId);
        if (! $syncStatus) {
            return;
        }

        // Skip if the app is currently being full-synced.
        if ($syncStatus->status === SyncStatus::STATUS_PROCESSING && $syncStatus->current_step !== SyncStatus::STEP_RECONCILING) {
            return;
        }

        $app = App::find($syncStatus->app_id);
        if (! $app) {
            return;
        }

        $items = $syncStatus->failed_items ?? [];
        if (empty($items)) {
            return;
        }

        $syncStatus->update(['current_step' => SyncStatus::STEP_RECONCILING]);

        $maxAttempts = config('appstorecat.sync.item_retry.max_attempts', []);
        $backoff = config('appstorecat.sync.item_retry.backoff_seconds', [300, 900, 1800, 3600, 7200, 21600, 43200]);

        $updated = [];
        $now = now();

        foreach ($items as $item) {
            if (($item['permanent_failure'] ?? false) === true) {
                $updated[] = $item;
                continue;
            }

            $nextRetryAt = isset($item['next_retry_at']) ? Carbon::parse($item['next_retry_at']) : $now;
            if ($nextRetryAt->gt($now)) {
                $updated[] = $item;
                continue;
            }

            $success = $syncer->retryFailedItem($app, $item);

            if ($success) {
                // Drop from failed_items.
                continue;
            }

            $retryCount = ((int) ($item['retry_count'] ?? 0)) + 1;
            $reason = $item['reason'] ?? SyncStatus::REASON_NETWORK_ERROR;
            $maxForReason = (int) ($maxAttempts[$reason] ?? 10);

            $item['retry_count'] = $retryCount;
            $item['last_attempted_at'] = $now->toIso8601String();

            if ($retryCount >= $maxForReason) {
                $item['permanent_failure'] = true;
                $item['next_retry_at'] = null;
            } else {
                $backoffIndex = min($retryCount - 1, count($backoff) - 1);
                $delay = $backoff[max(0, $backoffIndex)];
                $item['next_retry_at'] = $now->copy()->addSeconds($delay)->toIso8601String();
            }

            $updated[] = $item;
        }

        $allPermanent = collect($updated)->every(fn ($i) => ($i['permanent_failure'] ?? false) === true);
        $empty = empty($updated);

        $earliestRetry = collect($updated)
            ->filter(fn ($i) => ! ($i['permanent_failure'] ?? false) && ! empty($i['next_retry_at']))
            ->pluck('next_retry_at')
            ->map(fn ($dt) => Carbon::parse($dt))
            ->min();

        $syncStatus->forceFill([
            'failed_items' => array_values($updated),
            'next_retry_at' => $earliestRetry,
            'current_step' => null,
            'status' => ($empty || $allPermanent) ? SyncStatus::STATUS_COMPLETED : SyncStatus::STATUS_COMPLETED,
            'completed_at' => $syncStatus->completed_at ?? $now,
        ])->save();

        Log::info('Reconcile run completed', [
            'app_id' => $app->id,
            'external_id' => $app->external_id,
            'remaining_failed_items' => count($updated),
            'all_permanent' => $allPermanent,
        ]);
    }
}
