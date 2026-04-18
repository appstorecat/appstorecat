<?php

declare(strict_types=1);

namespace App\Jobs\Chart;

use App\Connectors\GooglePlayConnector;
use App\Connectors\ITunesLookupConnector;
use App\Enums\DiscoverSource;
use App\Models\App;
use App\Models\ChartEntry;
use App\Models\ChartSnapshot;
use App\Models\StoreCategory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class SyncChartSnapshotJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $uniqueFor = 3600;

    /** @var array<int> */
    public array $backoff = [60, 300];

    public function __construct(
        private readonly string $platform,
        private readonly string $collection,
        private readonly string $country,
        private readonly int $categoryId,
    ) {}

    public function uniqueId(): string
    {
        return "{$this->platform}:{$this->collection}:{$this->country}:{$this->categoryId}";
    }

    public function handle(ITunesLookupConnector $ios, GooglePlayConnector $android): void
    {
        $today = now()->toDateString();

        $exists = ChartSnapshot::forChart($this->platform, $this->collection, $this->country, $this->categoryId)
            ->where('snapshot_date', $today)
            ->exists();

        if ($exists) {
            return;
        }

        $connectorKey = $this->platform === 'ios' ? 'appstore' : 'gplay';
        $jobsPerMin = (int) config("appstorecat.connectors.{$connectorKey}.throttle.chart_jobs", 5);

        Redis::throttle("chart-job:{$this->platform}")
            ->allow($jobsPerMin)
            ->every(60)
            ->block(300)
            ->then(function () use ($ios, $android, $today) {
                $this->fetchAndStore($ios, $android, $today);
            });
    }

    private function fetchAndStore(ITunesLookupConnector $ios, GooglePlayConnector $android, string $today): void
    {
        $start = microtime(true);
        $connector = $this->platform === 'ios' ? $ios : $android;
        $categoryExternalId = StoreCategory::find($this->categoryId)?->external_id;

        try {
            $results = $connector->fetchChart($this->collection, $this->country, $categoryExternalId);
        } catch (\Throwable $e) {
            Log::warning('Chart fetch failed', [
                'platform' => $this->platform,
                'collection' => $this->collection,
                'country' => $this->country,
                'category_id' => $this->categoryId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
        $fetchMs = round((microtime(true) - $start) * 1000);

        if (empty($results)) {
            Log::info('Chart sync returned empty', [
                'platform' => $this->platform,
                'collection' => $this->collection,
                'country' => $this->country,
                'category_id' => $this->categoryId,
                'fetch_ms' => $fetchMs,
            ]);

            return;
        }

        $snapshot = ChartSnapshot::create([
            'platform' => $this->platform,
            'collection' => $this->collection,
            'category_id' => $this->categoryId,
            'country' => $this->country,
            'snapshot_date' => $today,
        ]);

        $newApps = 0;
        foreach ($results as $entry) {
            if ($categoryExternalId !== null && empty($entry['genre_id'])) {
                $entry['genre_id'] = $categoryExternalId;
            }

            $app = App::discover($this->platform, $entry['app_id'], $entry, DiscoverSource::Trending, $this->country);

            if (! $app) {
                continue;
            }

            if ($app->wasRecentlyCreated) {
                $newApps++;
            }

            ChartEntry::create([
                'trending_chart_id' => $snapshot->id,
                'rank' => $entry['rank'],
                'app_id' => $app->id,
                'price' => $entry['price'] ?? 0,
                'currency' => $entry['currency'] ?? null,
            ]);
        }

        $totalMs = round((microtime(true) - $start) * 1000);

        Log::info('Chart sync completed', [
            'platform' => $this->platform,
            'collection' => $this->collection,
            'country' => $this->country,
            'category_id' => $this->categoryId,
            'entries' => count($results),
            'new_apps' => $newApps,
            'fetch_ms' => $fetchMs,
            'total_ms' => $totalMs,
        ]);
    }
}
