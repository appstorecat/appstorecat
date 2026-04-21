<?php

declare(strict_types=1);

namespace App\Console\Commands\Apps;

use App\Jobs\Sync\SyncAppJob;
use App\Models\App;
use Illuminate\Console\Command;

class SyncTrackedCommand extends Command
{
    protected $signature = 'appstorecat:apps:sync-tracked {--ios} {--android}';

    protected $description = 'Dispatch sync jobs for all tracked apps that need syncing';

    public function handle(): int
    {
        $platform = $this->resolvePlatform();

        if ($platform && ! config("appstorecat.sync.{$platform}.tracked_app_sync_enabled")) {
            return self::SUCCESS;
        }

        $apps = $this->findPendingApps($platform);

        if ($apps->isEmpty()) {
            $this->components->info('No tracked apps need syncing.'.($platform ? " ({$platform})" : ''));

            return self::SUCCESS;
        }

        $apps->each(function (App $app) {
            SyncAppJob::dispatch($app->id)->onQueue('sync-tracked-'.$app->platform->slug());
        });

        $this->components->info("Dispatched {$apps->count()} tracked sync jobs.".($platform ? " ({$platform})" : ''));

        return self::SUCCESS;
    }

    private function resolvePlatform(): ?string
    {
        if ($this->option('ios')) {
            return 'ios';
        }
        if ($this->option('android')) {
            return 'android';
        }

        return null;
    }

    private function findPendingApps(?string $platform)
    {
        $query = App::whereHas('users')
            ->where('is_available', true);

        if ($platform) {
            $query->platform($platform);
        }

        return $query->where(function ($q) use ($platform) {
            $q->whereNull('last_synced_at');

            if ($platform) {
                $staleHours = config("appstorecat.sync.{$platform}.tracked_app_refresh_hours", 24);
                $q->orWhere('last_synced_at', '<', now()->subHours($staleHours));
            } else {
                $q->orWhere(function ($q2) {
                    $q2->platform('ios')
                        ->where('last_synced_at', '<', now()->subHours(config('appstorecat.sync.ios.tracked_app_refresh_hours', 24)));
                })->orWhere(function ($q2) {
                    $q2->platform('android')
                        ->where('last_synced_at', '<', now()->subHours(config('appstorecat.sync.android.tracked_app_refresh_hours', 24)));
                });
            }
        })->orderBy('last_synced_at')->limit($this->batchLimit($platform))->get();
    }

    private function batchLimit(?string $platform): int
    {
        if ($platform) {
            return (int) config("appstorecat.sync.{$platform}.tracked_batch_size", 5);
        }

        // No platform filter: schedule both platforms worth of work in one tick.
        return (int) config('appstorecat.sync.ios.tracked_batch_size', 5)
            + (int) config('appstorecat.sync.android.tracked_batch_size', 5);
    }
}
