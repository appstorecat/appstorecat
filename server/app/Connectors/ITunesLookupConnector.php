<?php

namespace App\Connectors;

use App\Models\App;
use Illuminate\Support\Facades\Http;
use Throwable;

class ITunesLookupConnector implements ConnectorInterface
{
    public function supports(string $platform): bool
    {
        return $platform === 'ios';
    }

    public function fetchIdentity(App $app, string $country = 'us'): ConnectorResult
    {
        try {
            $data = $this->get("/apps/{$app->external_id}/identity", ['country' => $country]);
        } catch (Throwable $e) {
            return ConnectorResult::failure('App Store fetch failed: '.$e->getMessage());
        }

        return ConnectorResult::success([
            'name' => $data['name'] ?? '',
            'publisher_name' => $data['publisher_name'] ?? '',
            'publisher_external_id' => $data['publisher_external_id'] ?? null,
            'publisher_url' => $data['publisher_url'] ?? null,
            'category_primary' => $data['category'] ?? '',
            'category_external_id' => $data['category_id'] ?? null,
            'content_rating' => $data['content_rating'] ?? null,
            'supported_locales' => $data['supported_locales'] ?? [],
            'original_release_date' => $data['original_release_date'] ?? null,
            'is_free' => ($data['price_model'] ?? 'free') === 'free',
            'external_id' => $data['app_id'] ?? $app->external_id,
            'version' => $data['version'] ?? null,
            'current_version_release_date' => $data['current_version_release_date'] ?? null,
        ]);
    }

    public function fetchListings(App $app, string $country = 'us', ?string $locale = null): ConnectorResult
    {
        try {
            $query = ['country' => $country];
            if ($locale) {
                $query['lang'] = $locale;
            }
            $data = $this->get("/apps/{$app->external_id}/listings", $query);
        } catch (Throwable $e) {
            return ConnectorResult::failure('App Store fetch failed: '.$e->getMessage());
        }

        return ConnectorResult::success($this->mapListingData($data, $locale));
    }

    public function fetchMetrics(App $app, string $country = 'us'): ConnectorResult
    {
        try {
            $data = $this->get("/apps/{$app->external_id}/metrics", ['country' => $country]);
        } catch (Throwable $e) {
            return ConnectorResult::failure('App Store fetch failed: '.$e->getMessage());
        }

        return ConnectorResult::success([
            'rating' => $data['rating'] ?? 0,
            'rating_count' => $data['rating_count'] ?? 0,
            'rating_breakdown' => $data['rating_breakdown'] ?? null,
            'file_size_bytes' => $data['file_size_bytes'] ?? null,
        ]);
    }

    public function fetchDeveloperApps(string $developerExternalId): ConnectorResult
    {
        try {
            $data = $this->get("/developers/{$developerExternalId}/apps");
        } catch (Throwable $e) {
            return ConnectorResult::failure('App Store developer apps fetch failed: '.$e->getMessage());
        }

        $apps = [];
        foreach ($data['apps'] ?? [] as $info) {
            $apps[] = [
                'external_id' => $info['external_id'] ?? '',
                'name' => $info['name'] ?? '',
                'icon_url' => $info['icon_url'] ?? null,
                'rating' => $info['rating'] ?? null,
                'rating_count' => $info['rating_count'] ?? null,
                'is_free' => ($info['price_model'] ?? 'free') === 'free',
                'category' => $info['category'] ?? null,
            ];
        }

        return ConnectorResult::success(['apps' => $apps]);
    }

    public function fetchSearch(string $term, int $limit = 10, string $country = 'us'): array
    {
        $data = $this->get('/apps/search', ['term' => $term, 'limit' => $limit, 'country' => $country]);

        return $data['results'] ?? [];
    }

    public function fetchChart(string $collection, string $country, ?string $categoryExternalId = null): array
    {
        $query = [
            'collection' => $collection,
            'country' => $country,
            'num' => 200,
        ];

        // null external_id = "All" sentinel → omit category to fetch overall chart.
        if ($categoryExternalId !== null) {
            $query['category'] = (int) $categoryExternalId;
        }

        $data = $this->get('/charts', $query);

        return $data['results'] ?? [];
    }

    public function getSourceName(): string
    {
        return 'appstore';
    }

    private function mapListingData(array $data, ?string $locale = null): array
    {
        $description = $data['description'] ?? '';
        $screenshots = [];

        foreach ($data['screenshots'] ?? [] as $screenshot) {
            $screenshots[] = [
                'url' => $screenshot['url'] ?? '',
                'device_type' => $screenshot['device_type'] ?? 'iphone',
                'order' => $screenshot['order'] ?? 0,
            ];
        }

        return [
            'platform' => 'ios',
            'locale' => $locale,
            'title' => $data['title'] ?? '',
            'subtitle' => $data['subtitle'] ?? null,
            'description' => $description,
            'whats_new' => $data['whats_new'] ?? null,
            'icon_url' => $data['icon_url'] ?? null,
            'screenshots' => $screenshots,
            'video_url' => $data['video_url'] ?? null,
            'price' => $data['price'] ?? 0,
            'currency' => $data['currency'] ?? null,
        ];
    }

    private function get(string $path, array $query = []): array
    {
        $baseUrl = config('appstorecat.connectors.appstore.base_url');
        $timeout = config('appstorecat.connectors.appstore.timeout', 30);

        $response = Http::timeout($timeout)->get("{$baseUrl}{$path}", $query);

        if ($response->failed()) {
            throw new \RuntimeException("Scraper API request failed: {$response->status()} - ".($response->json('error') ?? $response->body()));
        }

        return $response->json();
    }
}
