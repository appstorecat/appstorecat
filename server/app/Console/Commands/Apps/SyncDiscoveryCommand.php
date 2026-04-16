<?php

declare(strict_types=1);

namespace App\Console\Commands\Apps;

use App\Jobs\Sync\SyncAppJob;
use App\Models\App;
use Illuminate\Console\Command;

class SyncDiscoveryCommand extends Command
{
    protected $signature = 'appstorecat:apps:sync-discovery {--ios} {--android}';

    protected $description = 'Dispatch sync jobs for all untracked apps that need syncing';

    public function handle(): int
    {
        $platform = $this->resolvePlatform();

        if ($platform && ! config("appstorecat.sync.{$platform}.discovery_app_sync_enabled")) {
            return self::SUCCESS;
        }

        $apps = $this->findPendingApps($platform);

        if ($apps->isEmpty()) {
            $this->components->info('No untracked apps need syncing.' . ($platform ? " ({$platform})" : ''));

            return self::SUCCESS;
        }

        $apps->each(function (App $app) {
            SyncAppJob::dispatch($app->id)->onQueue('sync-discovery-' . $app->platform->value);
        });

        $this->components->info("Dispatched {$apps->count()} discovery sync jobs." . ($platform ? " ({$platform})" : ''));

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
        $query = App::doesntHave('users')
            ->where('is_available', true);

        if ($platform) {
            $query->where('platform', $platform);
        }

        return $query->where(function ($q) use ($platform) {
            $q->whereNull('last_synced_at');

            if ($platform) {
                $staleHours = config("appstorecat.sync.{$platform}.discovery_app_refresh_hours", 24);
                $q->orWhere('last_synced_at', '<', now()->subHours($staleHours));
            } else {
                $q->orWhere(function ($q2) {
                    $q2->where('platform', 'ios')
                        ->where('last_synced_at', '<', now()->subHours(config('appstorecat.sync.ios.discovery_app_refresh_hours', 24)));
                })->orWhere(function ($q2) {
                    $q2->where('platform', 'android')
                        ->where('last_synced_at', '<', now()->subHours(config('appstorecat.sync.android.discovery_app_refresh_hours', 24)));
                });
            }
        })->orderBy('last_synced_at')->limit(100)->get();
    }
}
