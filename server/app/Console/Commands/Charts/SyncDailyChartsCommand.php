<?php

declare(strict_types=1);

namespace App\Console\Commands\Charts;

use App\Jobs\Chart\SyncChartSnapshotJob;
use App\Models\ChartSnapshot;
use App\Models\Country;
use App\Models\StoreCategory;
use Illuminate\Console\Command;

class SyncDailyChartsCommand extends Command
{
    protected $signature = 'appstorecat:charts:sync-daily {--ios} {--android}';

    protected $description = 'Dispatch chart sync jobs for all active countries and categories';

    public function handle(): int
    {
        if ($this->option('ios')) {
            $platforms = ['ios'];
        } elseif ($this->option('android')) {
            $platforms = ['android'];
        } else {
            $platforms = ['ios', 'android'];
        }

        $collections = ['top_free', 'top_paid', 'top_grossing'];

        $jobCount = 0;

        $today = now()->toDateString();

        foreach ($platforms as $platform) {
            if (! config("appstorecat.charts.{$platform}.daily_sync_enabled")) {
                $this->components->info("  {$platform}: daily chart sync is disabled.");
                continue;
            }

            $existingSnapshots = ChartSnapshot::platform($platform)
                ->where('snapshot_date', $today)
                ->get()
                ->map(fn ($s) => "{$s->collection->value}:{$s->country}:{$s->category_id}")
                ->flip();

            $countries = Country::activeForPlatform($platform)
                ->orderByDesc('priority')
                ->orderBy('name')
                ->pluck('code');

            $categories = StoreCategory::platform($platform)
                ->where('type', 'app')
                ->orderByDesc('priority')
                ->orderBy('name')
                ->get();

            $skipped = 0;

            foreach ($countries as $country) {
                foreach ($collections as $collection) {
                    foreach ($categories as $category) {
                        if (! $existingSnapshots->has("{$collection}:{$country}:{$category->id}")) {
                            SyncChartSnapshotJob::dispatch($platform, $collection, $country, $category->id)
                                ->onQueue("charts-{$platform}");
                            $jobCount++;
                        } else {
                            $skipped++;
                        }
                    }
                }
            }

            $this->components->info("  {$platform}: {$jobCount} dispatched, {$skipped} skipped (already synced)");
        }

        $this->components->info("Dispatched {$jobCount} chart sync jobs.");

        return self::SUCCESS;
    }
}
