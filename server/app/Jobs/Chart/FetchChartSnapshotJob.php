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
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class FetchChartSnapshotJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int> */
    public array $backoff = [30, 60, 120];

    public function __construct(
        private readonly string $platform,
        private readonly string $collection,
        private readonly string $countryCode,
        private readonly int $categoryId,
    ) {}

    public function handle(ITunesLookupConnector $ios, GooglePlayConnector $android): void
    {
        $today = now()->toDateString();

        $exists = ChartSnapshot::forChart($this->platform, $this->collection, $this->countryCode, $this->categoryId)
            ->where('snapshot_date', $today)
            ->exists();

        if ($exists) {
            return;
        }

        $this->fetchAndStore($ios, $android, $today);
    }

    private function fetchAndStore(ITunesLookupConnector $ios, GooglePlayConnector $android, string $today): void
    {
        $connector = $this->platform === 'ios' ? $ios : $android;
        $categoryExternalId = StoreCategory::find($this->categoryId)?->external_id;

        $results = $connector->fetchChart($this->collection, $this->countryCode, $categoryExternalId);

        if (empty($results)) {
            Log::warning('Chart fetch returned empty', [
                'platform' => $this->platform,
                'collection' => $this->collection,
                'country_code' => $this->countryCode,
            ]);

            return;
        }

        $snapshot = ChartSnapshot::create([
            'platform' => $this->platform,
            'collection' => $this->collection,
            'category_id' => $this->categoryId,
            'country_code' => $this->countryCode,
            'snapshot_date' => $today,
        ]);

        foreach ($results as $entry) {
            if ($categoryExternalId !== null && empty($entry['genre_id'])) {
                $entry['genre_id'] = $categoryExternalId;
            }

            $app = App::discover($this->platform, $entry['app_id'], $entry, DiscoverSource::Trending, $this->countryCode);

            if (! $app) {
                continue;
            }

            ChartEntry::create([
                'trending_chart_id' => $snapshot->id,
                'rank' => $entry['rank'],
                'app_id' => $app->id,
                'price' => $entry['price'] ?? 0,
                'currency' => $entry['currency'] ?? null,
            ]);
        }
    }
}
