<?php

declare(strict_types=1);

namespace App\Jobs\Sync;

use App\Models\App;
use App\Services\AppSyncer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class SyncAppJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int> */
    public array $backoff = [30, 60, 120];

    public int $uniqueFor = 3600;

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

        $platform = $app->isIos() ? 'appstore' : 'gplay';
        $jobsPerMin = (int) config("appstorecat.connectors.{$platform}.throttle.sync_jobs", 3);

        Redis::throttle("sync-job:{$app->platform->value}")
            ->allow($jobsPerMin)
            ->every(60)
            ->block(300)
            ->then(function () use ($syncer, $app) {
                $start = microtime(true);
                $syncer->syncAll($app);
                $totalMs = round((microtime(true) - $start) * 1000);

                Log::info('App sync completed', [
                    'app_id' => $app->id,
                    'external_id' => $app->external_id,
                    'platform' => $app->platform->value,
                    'total_ms' => $totalMs,
                ]);
            });
    }
}
