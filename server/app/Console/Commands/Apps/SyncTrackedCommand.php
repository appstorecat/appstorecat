<?php

declare(strict_types=1);

namespace App\Console\Commands\Apps;

use App\Jobs\Sync\SyncAppJob;
use App\Models\App;
use App\Models\AppCompetitor;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class SyncTrackedCommand extends Command
{
    protected $signature = 'appstorecat:apps:sync-tracked {--ios} {--android}';

    protected $description = 'Dispatch sync jobs for tracked apps, falling back to competitors then discovered apps to keep the pipeline busy';

    public function handle(): int
    {
        $platform = $this->resolvePlatform();

        if ($platform && ! config("appstorecat.sync.{$platform}.tracked_app_sync_enabled")) {
            return self::SUCCESS;
        }

        $apps = $this->findPendingApps($platform);

        if ($apps->isEmpty()) {
            $this->components->info('No apps need syncing this tick.'.($platform ? " ({$platform})" : ''));

            return self::SUCCESS;
        }

        $apps->each(function (App $app) {
            SyncAppJob::dispatch($app->id)->onQueue('sync-tracked-'.$app->platform->slug());
        });

        $this->components->info("Dispatched {$apps->count()} sync jobs.".($platform ? " ({$platform})" : ''));

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

    /**
     * Select up to the platform's batch size by tier, in order:
     *   1. Tracked apps (user_apps)
     *   2. Competitor apps (app_competitors.competitor_app_id) that are not tracked
     *   3. Apps that are neither tracked nor a competitor
     * Inside every tier: apps with no prior sync come first, then oldest-first.
     *
     * @return Collection<int, App>
     */
    private function findPendingApps(?string $platform): Collection
    {
        $limit = $this->batchLimit($platform);
        $selected = collect();

        $trackedIds = $this->trackedAppIds($platform);
        $this->fillFromQuery(
            $selected,
            $limit,
            fn () => $this->baseQuery($platform)->whereIn('id', $trackedIds),
        );

        if ($selected->count() < $limit) {
            $competitorIds = $this->competitorAppIds($platform)->diff($trackedIds);
            $this->fillFromQuery(
                $selected,
                $limit,
                fn () => $this->baseQuery($platform)->whereIn('id', $competitorIds),
            );
        }

        if ($selected->count() < $limit) {
            $this->fillFromQuery(
                $selected,
                $limit,
                fn () => $this->baseQuery($platform)
                    ->whereNotIn('id', $trackedIds)
                    ->whereNotIn('id', $this->competitorAppIds($platform)),
            );
        }

        return $selected;
    }

    /**
     * Append rows from the given query (already tier-scoped) to $selected
     * until $limit is reached, skipping ids already picked.
     */
    private function fillFromQuery(Collection $selected, int $limit, \Closure $queryFactory): void
    {
        $remaining = $limit - $selected->count();
        if ($remaining <= 0) {
            return;
        }

        $query = $queryFactory();
        if ($selected->isNotEmpty()) {
            $query->whereNotIn('id', $selected->pluck('id'));
        }

        $rows = $query
            ->orderByRaw('last_synced_at IS NULL DESC')
            ->orderBy('last_synced_at')
            ->limit($remaining)
            ->get();

        foreach ($rows as $row) {
            $selected->push($row);
        }
    }

    /**
     * Base query shared by all tiers: available, platform-scoped, and stale
     * (never synced OR last synced longer than the refresh window ago).
     */
    private function baseQuery(?string $platform): Builder
    {
        $query = App::query()->where('is_available', true);

        if ($platform) {
            $query->platform($platform);
            $staleHours = (int) config("appstorecat.sync.{$platform}.tracked_app_refresh_hours", 24);
            $query->where(function ($q) use ($staleHours) {
                $q->whereNull('last_synced_at')
                    ->orWhere('last_synced_at', '<', now()->subHours($staleHours));
            });

            return $query;
        }

        // Platform-agnostic: each platform uses its own refresh window.
        $query->where(function ($q) {
            $iosHours = (int) config('appstorecat.sync.ios.tracked_app_refresh_hours', 24);
            $androidHours = (int) config('appstorecat.sync.android.tracked_app_refresh_hours', 24);

            $q->whereNull('last_synced_at')
                ->orWhere(function ($q2) use ($iosHours) {
                    $q2->platform('ios')->where('last_synced_at', '<', now()->subHours($iosHours));
                })
                ->orWhere(function ($q2) use ($androidHours) {
                    $q2->platform('android')->where('last_synced_at', '<', now()->subHours($androidHours));
                });
        });

        return $query;
    }

    /**
     * @return Collection<int, int>
     */
    private function trackedAppIds(?string $platform): Collection
    {
        $query = App::query()
            ->whereHas('users')
            ->when($platform, fn ($q) => $q->platform($platform))
            ->select('id');

        return $query->pluck('id');
    }

    /**
     * @return Collection<int, int>
     */
    private function competitorAppIds(?string $platform): Collection
    {
        $ids = AppCompetitor::query()->pluck('competitor_app_id')->unique();

        if (! $platform) {
            return $ids;
        }

        return App::query()
            ->whereIn('id', $ids)
            ->platform($platform)
            ->pluck('id');
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
