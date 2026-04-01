<?php

declare(strict_types=1);

namespace App\Console\Commands\Charts;

use App\Jobs\Chart\SyncChartSnapshotJob;
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

        foreach ($platforms as $platform) {
            if (! config("appstorecat.charts.{$platform}.daily_sync_enabled")) {
                $this->components->info("  {$platform}: daily chart sync is disabled.");
                continue;
            }

            $countries = Country::activeForPlatform($platform)
                ->orderByDesc('priority')
                ->orderBy('name')
                ->pluck('code');

            $categories = StoreCategory::where('platform', $platform)
                ->where('type', 'app')
                ->orderByDesc('priority')
                ->orderBy('name')
                ->get();

            foreach ($countries as $country) {
                foreach ($collections as $collection) {
                    SyncChartSnapshotJob::dispatch($platform, $collection, $country, null)
                        ->onQueue("charts-{$platform}");
                    $jobCount++;

                    foreach ($categories as $category) {
                        SyncChartSnapshotJob::dispatch($platform, $collection, $country, $category->id)
                            ->onQueue("charts-{$platform}");
                        $jobCount++;
                    }
                }
            }

            $this->components->info("  {$platform}: {$jobCount} jobs dispatched");
        }

        $this->components->info("Dispatched {$jobCount} chart sync jobs.");

        return self::SUCCESS;
    }
}
