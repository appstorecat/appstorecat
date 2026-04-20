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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class SyncAppJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int> */
    public array $backoff = [30, 60, 120];

    public int $uniqueFor = 3600;

    public int $timeout = 600;

    public function __construct(
        private readonly int $appId,
    ) {}

    public function uniqueId(): string
    {
        return "sync-app-{$this->appId}";
    }

    public function handle(AppSyncer $syncer): void
    {
        $app = App::find($this->appId);

        if (! $app) {
            return;
        }

        $syncStatus = SyncStatus::firstOrCreate(
            ['app_id' => $app->id],
            ['status' => SyncStatus::STATUS_QUEUED],
        );

        // Duplicate guard — another worker already processing.
        if ($syncStatus->status === SyncStatus::STATUS_PROCESSING && $syncStatus->job_id !== null) {
            return;
        }

        $syncStatus->forceFill([
            'job_id' => $this->job?->getJobId() ?? (string) Str::uuid(),
        ])->save();

        $start = microtime(true);
        try {
            $syncer->syncAll($app, $syncStatus);
        } catch (Throwable $e) {
            $syncStatus->forceFill([
                'status' => SyncStatus::STATUS_FAILED,
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
            ])->save();
            throw $e;
        }
        $totalMs = round((microtime(true) - $start) * 1000);

        Log::info('App sync completed', [
            'app_id' => $app->id,
            'external_id' => $app->external_id,
            'platform' => $app->platform->slug(),
            'total_ms' => $totalMs,
            'failed_items_count' => count($syncStatus->fresh()->failed_items ?? []),
        ]);
    }
}
