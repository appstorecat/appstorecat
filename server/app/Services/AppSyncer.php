<?php

declare(strict_types=1);

namespace App\Services;

use App\Connectors\ConnectorInterface;
use App\Connectors\GooglePlayConnector;
use App\Connectors\ITunesLookupConnector;
use App\Models\App;
use App\Models\AppMetric;
use App\Models\AppVersion;
use App\Models\Country;
use App\Models\Publisher;
use App\Models\Review;
use App\Models\StoreListing;
use App\Models\StoreListingChange;
use App\Services\StoreCategoryResolver;
use Illuminate\Support\Facades\Log;

class AppSyncer
{
    public function __construct(
        private readonly ITunesLookupConnector $ios,
        private readonly GooglePlayConnector $android,
        private readonly StoreCategoryResolver $categoryResolver,
    ) {}

    /**
     * Run all sync steps. Called by SyncAppJob.
     */
    public function syncAll(App $app): void
    {
        $identityData = $this->syncIdentity($app);
        $version = $this->saveVersion($app, $identityData);
        $this->syncListing($app, $version);
        $this->detectLocaleChanges($app, $version);
        $this->syncMetrics($app, $version);
        $platform = $app->platform->value;
        if (config("appstorecat.sync.{$platform}.reviews_enabled")) {
            $this->syncReviews($app);
        }

        if ($version) {
            $this->updateVersionDetails($app, $version);
        }

        $app->update(['last_synced_at' => now()]);
    }

    // ─── Identity ────────────────────────────────────────────────

    public function syncIdentity(App $app): array
    {
        $connector = $this->connector($app);

        try {
            $result = $connector->fetchIdentity($app, 'us');

            if (! $result->success && $app->origin_country !== 'us') {
                $result = $connector->fetchIdentity($app, $app->origin_country);
            }

            if (! $result->success) {
                $isNotFound = str_contains(strtolower($result->error ?? ''), '404')
                    || str_contains(strtolower($result->error ?? ''), 'not found');

                if ($isNotFound) {
                    $app->update(['is_available' => false]);
                }

                return [];
            }

            if (! $app->is_available) {
                $app->update(['is_available' => true]);
            }

            $data = $result->data;
            $platform = $app->platform->value;

            $appData = collect($data)->only([
                'supported_locales', 'original_release_date', 'is_free',
                'content_rating', 'store_url', 'price_model',
            ])->toArray();

            $appData['display_name'] = $data['name'] ?? $app->display_name;
            $appData['display_icon'] = $data['icon_url'] ?? $app->display_icon;

            if (! empty($data['publisher_name']) && ! empty($data['publisher_external_id'])) {
                $publisher = Publisher::firstOrCreate(
                    ['platform' => $platform, 'external_id' => $data['publisher_external_id']],
                    ['name' => $data['publisher_name'], 'url' => $data['publisher_url'] ?? null],
                );
                $appData['publisher_id'] = $publisher->id;
            }

            $appData['category_id'] = $this->categoryResolver->resolveId(
                $platform,
                $data['category_external_id'] ?? null,
                $data['category_primary'] ?? null,
                ['source' => 'AppSyncer', 'external_id' => $app->external_id],
            );

            $app->update($appData);

            return $data;
        } catch (\Throwable $e) {
            Log::warning('Sync identity failed', ['app' => $app->external_id, 'error' => $e->getMessage()]);

            return [];
        }
    }

    // ─── Version ─────────────────────────────────────────────────

    public function saveVersion(App $app, array $identityData): ?AppVersion
    {
        $versionString = $identityData['version'] ?? null;

        if (! $versionString) {
            return null;
        }

        return AppVersion::firstOrCreate(
            ['app_id' => $app->id, 'version' => $versionString],
            [
                'release_date' => $identityData['current_version_release_date'] ?? null,
            ],
        );
    }

    // ─── Listing ─────────────────────────────────────────────────

    public function syncListing(App $app, ?AppVersion $version = null): void
    {
        $connector = $this->connector($app);
        $country = 'us';
        $language = $this->defaultLanguageForCountry($app, $country);

        try {
            $result = $connector->fetchListings($app, $country, $language);

            if (! $result->success && $app->origin_country !== 'us') {
                $country = $app->origin_country;
                $language = $this->defaultLanguageForCountry($app, $country);
                $result = $connector->fetchListings($app, $country, $language);
            }

            if (! $result->success) {
                return;
            }

            $this->saveListing($app, $result->data, $version);
        } catch (\Throwable $e) {
            Log::warning('Sync listing failed', ['app' => $app->external_id, 'error' => $e->getMessage()]);
        }
    }

    public function saveListing(App $app, array $data, ?AppVersion $version): StoreListing
    {
        $checksum = md5(($data['title'] ?? '').($data['description'] ?? ''));
        $language = $data['language'];

        $existing = StoreListing::where('app_id', $app->id)
            ->where('language', $language)
            ->orderByDesc('id')
            ->first();

        if ($existing && $existing->checksum !== $checksum) {
            $this->detectChanges($app, $existing, $data, $version);
        }

        $listing = StoreListing::updateOrCreate(
            [
                'app_id' => $app->id,
                'language' => $language,
            ],
            [
                'version_id' => $version?->id,
                'title' => $data['title'],
                'subtitle' => $data['subtitle'] ?? null,
                'description' => $data['description'],
                'whats_new' => $data['whats_new'] ?? null,
                'icon_url' => $data['icon_url'] ?? null,
                'screenshots' => $data['screenshots'] ?? [],
                'video_url' => $data['video_url'] ?? null,
                'price' => $data['price'] ?? 0,
                'currency' => $data['currency'] ?? null,
                'fetched_at' => now(),
                'checksum' => $checksum,
            ],
        );

        if ($listing->icon_url && ! $app->display_icon) {
            $app->update(['display_icon' => $listing->icon_url]);
        }

        if ($listing->description && $version) {
            app(KeywordAnalyzer::class)->analyzeFromListing($app, $listing);
        }

        return $listing;
    }

    // ─── Metrics ─────────────────────────────────────────────────

    public function syncMetrics(App $app, ?AppVersion $version = null): void
    {
        $connector = $this->connector($app);

        try {
            $result = $connector->fetchMetrics($app, 'us');

            if (! $result->success && $app->origin_country !== 'us') {
                $result = $connector->fetchMetrics($app, $app->origin_country);
            }

            if (! $result->success) {
                return;
            }

            $this->saveMetrics($app, $result->data, $version);
        } catch (\Throwable $e) {
            Log::warning('Sync metrics failed', ['app' => $app->external_id, 'error' => $e->getMessage()]);
        }
    }

    public function saveMetrics(App $app, array $data, ?AppVersion $version): void
    {
        $today = now()->format('Y-m-d');

        $previousMetric = AppMetric::where('app_id', $app->id)
            ->whereDate('date', '<', $today)
            ->orderByDesc('date')
            ->first();

        AppMetric::updateOrCreate(
            ['app_id' => $app->id, 'date' => $today],
            [
                'version_id' => $version?->id,
                'rating' => $data['rating'] ?? 0,
                'rating_count' => $data['rating_count'] ?? 0,
                'rating_delta' => $previousMetric
                    ? ($data['rating_count'] ?? 0) - $previousMetric->rating_count
                    : null,
                'installs_range' => $data['installs_range'] ?? null,
                'file_size_bytes' => $data['file_size_bytes'] ?? null,
                'rating_breakdown' => ! empty($data['rating_breakdown'])
                    ? $data['rating_breakdown']
                    : null,
            ],
        );
    }

    // ─── Reviews ─────────────────────────────────────────────────

    public function syncReviews(App $app): void
    {
        $connector = $this->connector($app);
        $country = $app->origin_country ?? 'us';
        $maxPages = $app->isIos() ? 10 : 1;

        try {
            for ($page = 1; $page <= $maxPages; $page++) {
                $result = $connector->fetchReviews($app, $country, $page);

                if (! $result->success || empty($result->data['reviews'])) {
                    break;
                }

                $newCount = $this->saveReviews($app, $result->data);

                if ($newCount === 0) {
                    break;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Sync reviews failed', ['app' => $app->external_id, 'error' => $e->getMessage()]);
        }
    }

    public function saveReviews(App $app, array $data): int
    {
        $newCount = 0;

        foreach ($data['reviews'] ?? [] as $review) {
            $record = Review::updateOrCreate(
                [
                    'app_id' => $app->id,
                    'external_id' => $review['external_id'],
                ],
                [
                    'country_code' => $app->isIos() ? strtolower($review['country_code'] ?? 'us') : null,
                    'author' => $review['author'] ?? null,
                    'title' => $review['title'] ?? null,
                    'body' => $review['body'] ?? null,
                    'rating' => $review['rating'] ?? 0,
                    'review_date' => $review['review_date'] ?? null,
                    'app_version' => $review['app_version'] ?? null,
                ],
            );

            if ($record->wasRecentlyCreated) {
                $newCount++;
            }
        }

        return $newCount;
    }

    // ─── Locale Change Detection ────────────────────────────────

    public function detectLocaleChanges(App $app, ?AppVersion $currentVersion): void
    {
        if (! $currentVersion) {
            return;
        }

        $previousVersion = AppVersion::where('app_id', $app->id)
            ->where('id', '<', $currentVersion->id)
            ->orderByDesc('id')
            ->first();

        if (! $previousVersion) {
            return;
        }

        $previousLanguages = StoreListing::where('app_id', $app->id)
            ->where('version_id', $previousVersion->id)
            ->pluck('language')
            ->toArray();

        $currentLanguages = StoreListing::where('app_id', $app->id)
            ->where('version_id', $currentVersion->id)
            ->pluck('language')
            ->toArray();

        $added = array_diff($currentLanguages, $previousLanguages);
        $removed = array_diff($previousLanguages, $currentLanguages);

        foreach ($added as $language) {
            $listing = StoreListing::where('app_id', $app->id)
                ->where('version_id', $currentVersion->id)
                ->where('language', $language)
                ->first();

            StoreListingChange::create([
                'app_id' => $app->id,
                'version_id' => $currentVersion->id,
                'language' => $language,
                'field_changed' => 'language_added',
                'old_value' => null,
                'new_value' => $listing?->title,
                'detected_at' => now(),
            ]);
        }

        foreach ($removed as $language) {
            $listing = StoreListing::where('app_id', $app->id)
                ->where('version_id', $previousVersion->id)
                ->where('language', $language)
                ->first();

            StoreListingChange::create([
                'app_id' => $app->id,
                'version_id' => $currentVersion->id,
                'language' => $language,
                'field_changed' => 'language_removed',
                'old_value' => $listing?->title,
                'new_value' => null,
                'detected_at' => now(),
            ]);
        }
    }

    // ─── Field Change Detection ──────────────────────────────────

    public function detectChanges(App $app, StoreListing $existing, array $newData, ?AppVersion $version): void
    {
        $fields = [
            'title' => $newData['title'] ?? '',
            'subtitle' => $newData['subtitle'] ?? null,
            'description' => $newData['description'] ?? '',
            'whats_new' => $newData['whats_new'] ?? null,
        ];

        foreach ($fields as $field => $newValue) {
            $oldValue = $existing->{$field};
            if ($oldValue !== $newValue) {
                StoreListingChange::create([
                    'app_id' => $app->id,
                    'version_id' => $version?->id,
                    'language' => $existing->language,
                    'field_changed' => $field,
                    'old_value' => $oldValue,
                    'new_value' => $newValue,
                    'detected_at' => now(),
                ]);
            }
        }

    }

    // ─── Version Details ─────────────────────────────────────────

    public function updateVersionDetails(App $app, AppVersion $version): void
    {
        $defaultLang = $this->defaultLanguageForCountry($app, $app->origin_country ?? 'us');
        $listing = StoreListing::where('app_id', $app->id)
            ->where('language', $defaultLang)
            ->orderByDesc('fetched_at')
            ->first();

        $metric = AppMetric::where('app_id', $app->id)
            ->where('version_id', $version->id)
            ->first();

        $version->update([
            'whats_new' => $listing?->whats_new,
            'file_size_bytes' => $metric?->file_size_bytes,
        ]);
    }

    // ─── Helpers ─────────────────────────────────────────────────

    public function syncListingForCountry(App $app, string $countryCode, ?string $language = null, ?AppVersion $version = null): StoreListing
    {
        $connector = $this->connector($app);
        $result = $connector->fetchListings($app, $countryCode, $language);

        if (! $result->success) {
            throw new \RuntimeException('Failed to fetch listing for country: '.$countryCode);
        }

        return $this->saveListing($app, $result->data, $version);
    }

    public function syncReviewsForCountry(App $app, string $countryCode): void
    {
        $connector = $this->connector($app);
        $maxPages = $app->isIos() ? 10 : 1;

        try {
            for ($page = 1; $page <= $maxPages; $page++) {
                $result = $connector->fetchReviews($app, $countryCode, $page);

                if (! $result->success || empty($result->data['reviews'])) {
                    break;
                }

                $newCount = $this->saveReviews($app, $result->data);

                if ($newCount === 0) {
                    break;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Sync reviews for country failed', ['app' => $app->external_id, 'country' => $countryCode]);
        }
    }

    private function defaultLanguageForCountry(App $app, string $countryCode): ?string
    {
        $country = Country::find($countryCode);
        if (! $country) {
            return null;
        }

        $languages = $app->isIos() ? $country->ios_languages : $country->android_languages;

        return $languages[0] ?? null;
    }

    private function connector(App $app): ConnectorInterface
    {
        return $app->isIos() ? $this->ios : $this->android;
    }
}
