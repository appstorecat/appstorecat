<?php

declare(strict_types=1);

namespace App\Services;

use App\Connectors\ConnectorInterface;
use App\Connectors\ConnectorResult;
use App\Connectors\GooglePlayConnector;
use App\Connectors\ITunesLookupConnector;
use App\Models\App;
use App\Models\AppMetric;
use App\Models\AppVersion;
use App\Models\Country;
use App\Models\Publisher;
use App\Models\StoreListing;
use App\Models\StoreListingChange;
use App\Models\SyncStatus;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class AppSyncer
{
    public function __construct(
        private readonly ITunesLookupConnector $ios,
        private readonly GooglePlayConnector $android,
        private readonly StoreCategoryResolver $categoryResolver,
    ) {}

    /**
     * Run the full sync pipeline. Called by SyncAppJob.
     *
     * The pipeline is broken into phases so each phase can update sync_status
     * and accumulate failed_items without aborting the whole job on a single
     * locale/country error.
     */
    public function syncAll(App $app, ?SyncStatus $syncStatus = null): void
    {
        $syncStatus = $syncStatus ?? $this->ensureSyncStatus($app);
        $syncStatus->forceFill([
            'status' => SyncStatus::STATUS_PROCESSING,
            'current_step' => SyncStatus::STEP_IDENTITY,
            'progress_done' => 0,
            'progress_total' => 0,
            'failed_items' => [],
            'error_message' => null,
            'started_at' => $syncStatus->started_at ?? now(),
            'completed_at' => null,
        ])->save();

        // Phase 1 — Identity (critical). If identity fails, syncIdentity
        // already marks the sync as failed and returns []. Abort the rest of
        // the pipeline — there's no point running listings/metrics against
        // an app we cannot resolve. See bug #4 in docs bugs/report_20apr.md.
        $identityData = $this->syncIdentity($app, $syncStatus);
        if (empty($identityData)) {
            $app->update(['last_synced_at' => now()]);

            return;
        }

        $version = $this->saveVersion($app, $identityData);

        // Phase 2 — Listings (multi-locale on iOS, single on Android fallback-to-locale loop)
        $syncStatus->update(['current_step' => SyncStatus::STEP_LISTINGS]);
        $this->syncListingsPhase($app, $version, $syncStatus);

        // Phase 3 — Metrics (multi-country)
        $syncStatus->update(['current_step' => SyncStatus::STEP_METRICS]);
        $this->syncMetricsPhase($app, $version, $syncStatus);

        // Phase 4 — Finalize (DB only)
        $syncStatus->update(['current_step' => SyncStatus::STEP_FINALIZE]);
        $this->detectLocaleChanges($app, $version);
        if ($version) {
            $this->updateVersionDetails($app, $version);
        }

        $app->update(['last_synced_at' => now()]);

        $syncStatus->forceFill([
            'status' => SyncStatus::STATUS_COMPLETED,
            'current_step' => null,
            'completed_at' => now(),
        ])->save();
    }

    private function ensureSyncStatus(App $app): SyncStatus
    {
        return SyncStatus::firstOrCreate(
            ['app_id' => $app->id],
            ['status' => SyncStatus::STATUS_PROCESSING],
        );
    }

    // ─── Phase 1: Identity ───────────────────────────────────────

    public function syncIdentity(App $app, ?SyncStatus $syncStatus = null): array
    {
        $connector = $this->connector($app);
        $attempts = (int) config('appstorecat.sync.item_retry.initial_attempts', 3);

        $result = $this->attempt($attempts, fn () => $connector->fetchIdentity($app, 'us'));

        if (! $result->success && $app->origin_country_code !== 'us') {
            $result = $this->attempt($attempts, fn () => $connector->fetchIdentity($app, $app->origin_country_code));
        }

        if (! $result->success) {
            $isNotFound = str_contains(strtolower($result->error ?? ''), '404')
                || str_contains(strtolower($result->error ?? ''), 'not found');

            if ($isNotFound) {
                $app->update(['is_available' => false]);
            }

            if ($syncStatus) {
                $syncStatus->update([
                    'status' => SyncStatus::STATUS_FAILED,
                    'error_message' => 'Identity fetch failed: '.($result->error ?? 'unknown'),
                    'completed_at' => now(),
                ]);
            }

            return [];
        }

        if (! $app->is_available) {
            $app->update(['is_available' => true]);
        }

        $data = $result->data;
        $platform = $app->platform->slug();

        $appData = collect($data)->only([
            'supported_locales', 'original_release_date', 'is_free',
            'content_rating', 'store_url', 'price_model',
        ])->toArray();

        $appData['display_name'] = $data['name'] ?? $app->display_name;
        $appData['icon_url'] = $data['icon_url'] ?? $app->icon_url;

        if (! empty($data['publisher_name']) && ! empty($data['publisher_external_id'])) {
            $publisher = Publisher::firstOrCreate(
                ['platform' => Publisher::normalizePlatform($platform), 'external_id' => $data['publisher_external_id']],
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
    }

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

    // ─── Phase 2: Listings ───────────────────────────────────────

    private function syncListingsPhase(App $app, ?AppVersion $version, SyncStatus $syncStatus): void
    {
        $map = $app->isIos() ? $this->iosLocaleMap() : $this->androidLocaleMap();

        $syncStatus->update([
            'progress_done' => 0,
            'progress_total' => count($map),
        ]);

        $done = 0;
        foreach ($map as $locale => $country) {
            $this->fetchAndSaveListing($app, $version, $country, $locale, $syncStatus);
            $done++;
            if ($done % 5 === 0 || $done === count($map)) {
                $syncStatus->update(['progress_done' => $done]);
            }
        }

        $syncStatus->update(['progress_done' => $done]);
    }

    private function fetchAndSaveListing(App $app, ?AppVersion $version, string $country, string $locale, SyncStatus $syncStatus): void
    {
        $connector = $this->connector($app);
        $attempts = (int) config('appstorecat.sync.item_retry.initial_attempts', 3);
        $lastError = null;

        for ($i = 1; $i <= $attempts; $i++) {
            try {
                $result = $connector->fetchListings($app, $country, $locale);
                if ($result->success) {
                    $this->saveListing($app, $result->data, $version);

                    return;
                }
                $lastError = $result->error;

                // 404/empty for a storefront is permanent — no row in
                // app_store_listings for this locale. Per-country availability
                // is tracked in app_metrics (saveMetric with isAvailable=false).
                if ($this->classifyError($lastError) === SyncStatus::REASON_EMPTY_RESPONSE) {
                    return;
                }
            } catch (Throwable $e) {
                $lastError = $e->getMessage();
            }
        }

        $this->pushFailedItem($syncStatus, [
            'type' => 'listing',
            'locale' => $locale,
            'country_code' => $country,
            'reason' => $this->classifyError($lastError),
            'retry_count' => 0,
            'last_attempted_at' => now()->toIso8601String(),
            'next_retry_at' => $this->nextRetryAt(1)->toIso8601String(),
            'permanent_failure' => false,
            'last_error' => $lastError,
        ]);
    }

    public function saveListing(App $app, array $data, ?AppVersion $version): StoreListing
    {
        $checksum = md5(
            ($data['title'] ?? '').
            ($data['subtitle'] ?? '').
            ($data['description'] ?? '').
            ($data['whats_new'] ?? '')
        );
        $locale = $data['locale'];

        $existing = StoreListing::where('app_id', $app->id)
            ->where('locale', $locale)
            ->orderByDesc('id')
            ->first();

        // Only treat this as a real field diff when the row we're comparing
        // against belongs to a strictly earlier version. Otherwise we'd log
        // phantom changes when the scraper upserts the same version twice in
        // one sync (e.g. partial/null fallbacks between passes).
        if (
            $existing
            && $version !== null
            && $existing->version_id !== null
            && $existing->version_id !== $version->id
            && $existing->checksum !== $checksum
        ) {
            $this->detectChanges($app, $existing, $data, $version);
        }

        $listing = StoreListing::updateOrCreate(
            [
                'app_id' => $app->id,
                'version_id' => $version?->id,
                'locale' => $locale,
            ],
            [
                'title' => $data['title'] ?? '',
                'subtitle' => $data['subtitle'] ?? null,
                'description' => $data['description'] ?? '',
                'promotional_text' => $data['promotional_text'] ?? null,
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

        if ($listing->icon_url && ! $app->icon_url) {
            $app->update(['icon_url' => $listing->icon_url]);
        }

        return $listing;
    }

    // ─── Phase 3: Metrics ────────────────────────────────────────

    private function syncMetricsPhase(App $app, ?AppVersion $version, SyncStatus $syncStatus): void
    {
        $countries = $app->isIos() ? $this->iosActiveCountries() : [AppMetric::GLOBAL_COUNTRY];

        $syncStatus->update([
            'progress_done' => 0,
            'progress_total' => count($countries),
        ]);

        $done = 0;
        foreach ($countries as $country) {
            $this->fetchAndSaveMetric($app, $version, $country, $syncStatus);
            $done++;
            if ($done % 10 === 0 || $done === count($countries)) {
                $syncStatus->update(['progress_done' => $done]);
            }
        }

        $syncStatus->update(['progress_done' => $done]);
    }

    private function fetchAndSaveMetric(App $app, ?AppVersion $version, string $country, SyncStatus $syncStatus): void
    {
        $connector = $this->connector($app);
        $attempts = (int) config('appstorecat.sync.item_retry.initial_attempts', 3);
        $fetchCountry = $country === AppMetric::GLOBAL_COUNTRY ? 'us' : $country;
        $lastError = null;

        for ($i = 1; $i <= $attempts; $i++) {
            try {
                $result = $connector->fetchMetrics($app, $fetchCountry);
                if ($result->success) {
                    $this->saveMetric($app, $version, $country, $result->data);

                    return;
                }
                $lastError = $result->error;

                // Empty response usually means app is not available in this storefront.
                if ($this->classifyError($lastError) === SyncStatus::REASON_EMPTY_RESPONSE) {
                    $this->saveMetric($app, $version, $country, [], isAvailable: false);

                    return;
                }
            } catch (Throwable $e) {
                $lastError = $e->getMessage();
            }
        }

        $this->pushFailedItem($syncStatus, [
            'type' => 'metric',
            'country_code' => $country,
            'reason' => $this->classifyError($lastError),
            'retry_count' => 0,
            'last_attempted_at' => now()->toIso8601String(),
            'next_retry_at' => $this->nextRetryAt(1)->toIso8601String(),
            'permanent_failure' => false,
            'last_error' => $lastError,
        ]);
    }

    public function saveMetric(App $app, ?AppVersion $version, string $countryCode, array $data, bool $isAvailable = true): void
    {
        $today = now()->format('Y-m-d');

        $previousMetric = AppMetric::where('app_id', $app->id)
            ->where('country_code', $countryCode)
            ->whereDate('date', '<', $today)
            ->orderByDesc('date')
            ->first();

        AppMetric::updateOrCreate(
            [
                'app_id' => $app->id,
                'country_code' => $countryCode,
                'date' => $today,
            ],
            [
                'version_id' => $version?->id,
                'rating' => $data['rating'] ?? 0,
                'rating_count' => $data['rating_count'] ?? 0,
                'rating_delta' => $previousMetric
                    ? ($data['rating_count'] ?? 0) - $previousMetric->rating_count
                    : null,
                'rating_breakdown' => ! empty($data['rating_breakdown']) ? $data['rating_breakdown'] : null,
                'price' => $data['price'] ?? null,
                'currency' => $data['currency'] ?? null,
                'installs_range' => $data['installs_range'] ?? null,
                'file_size_bytes' => $data['file_size_bytes'] ?? null,
                'is_available' => $isAvailable,
            ],
        );
    }

    // ─── Reconciliation helpers (called by ReconcileFailedItemsJob) ──

    /**
     * Retry a single failed item. Returns true on success, false on fail.
     */
    public function retryFailedItem(App $app, array $item, ?AppVersion $version = null): bool
    {
        $version = $version ?? AppVersion::where('app_id', $app->id)->orderByDesc('id')->first();

        try {
            if ($item['type'] === 'listing') {
                $connector = $this->connector($app);
                $result = $connector->fetchListings($app, $item['country_code'], $item['locale']);
                if ($result->success) {
                    $this->saveListing($app, $result->data, $version);

                    return true;
                }

                return false;
            }

            if ($item['type'] === 'metric') {
                $connector = $this->connector($app);
                $country = $item['country_code'];
                $fetchCountry = $country === AppMetric::GLOBAL_COUNTRY ? 'us' : $country;
                $result = $connector->fetchMetrics($app, $fetchCountry);
                if ($result->success) {
                    $this->saveMetric($app, $version, $country, $result->data);

                    return true;
                }

                return false;
            }
        } catch (Throwable $e) {
            Log::warning('Reconcile item failed', [
                'app' => $app->external_id,
                'item' => $item,
                'error' => $e->getMessage(),
            ]);
        }

        return false;
    }

    // ─── Locale / Field Change Detection ─────────────────────────

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

        $previousLocales = StoreListing::where('app_id', $app->id)
            ->where('version_id', $previousVersion->id)
            ->pluck('locale')
            ->toArray();

        $currentLocales = StoreListing::where('app_id', $app->id)
            ->where('version_id', $currentVersion->id)
            ->pluck('locale')
            ->toArray();

        $added = array_diff($currentLocales, $previousLocales);
        $removed = array_diff($previousLocales, $currentLocales);

        foreach ($added as $locale) {
            $listing = StoreListing::where('app_id', $app->id)
                ->where('version_id', $currentVersion->id)
                ->where('locale', $locale)
                ->first();

            StoreListingChange::firstOrCreate(
                [
                    'app_id' => $app->id,
                    'version_id' => $currentVersion->id,
                    'locale' => $locale,
                    'field_changed' => 'locale_added',
                ],
                [
                    'old_value' => null,
                    'new_value' => $listing?->title,
                    'detected_at' => now(),
                ],
            );
        }

        foreach ($removed as $locale) {
            $listing = StoreListing::where('app_id', $app->id)
                ->where('version_id', $previousVersion->id)
                ->where('locale', $locale)
                ->first();

            StoreListingChange::firstOrCreate(
                [
                    'app_id' => $app->id,
                    'version_id' => $currentVersion->id,
                    'locale' => $locale,
                    'field_changed' => 'locale_removed',
                ],
                [
                    'old_value' => $listing?->title,
                    'new_value' => null,
                    'detected_at' => now(),
                ],
            );
        }
    }

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
                StoreListingChange::firstOrCreate(
                    [
                        'app_id' => $app->id,
                        'version_id' => $version?->id,
                        'locale' => $existing->locale,
                        'field_changed' => $field,
                    ],
                    [
                        'old_value' => $oldValue,
                        'new_value' => $newValue,
                        'detected_at' => now(),
                    ],
                );
            }
        }
    }

    public function updateVersionDetails(App $app, AppVersion $version): void
    {
        $defaultLocale = $this->defaultLocaleForCountry($app, $app->origin_country_code ?? 'us');
        $listing = StoreListing::where('app_id', $app->id)
            ->where('locale', $defaultLocale)
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

    public function syncListingForCountry(App $app, string $countryCode, ?string $locale = null, ?AppVersion $version = null): StoreListing
    {
        $connector = $this->connector($app);
        $result = $connector->fetchListings($app, $countryCode, $locale);

        if (! $result->success) {
            throw new \RuntimeException('Failed to fetch listing for country: '.$countryCode);
        }

        return $this->saveListing($app, $result->data, $version);
    }

    private function defaultLocaleForCountry(App $app, string $countryCode): ?string
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

    /**
     * Attempt a callable up to $attempts times. Returns the last ConnectorResult.
     */
    private function attempt(int $attempts, callable $fn): ConnectorResult
    {
        $last = null;
        for ($i = 1; $i <= $attempts; $i++) {
            try {
                $result = $fn();
                if ($result->success) {
                    return $result;
                }
                $last = $result;
            } catch (Throwable $e) {
                $last = ConnectorResult::failure($e->getMessage());
            }
        }

        return $last ?? ConnectorResult::failure('no attempts made');
    }

    private function classifyError(?string $error): string
    {
        if (! $error) {
            return SyncStatus::REASON_NETWORK_ERROR;
        }

        $lower = strtolower($error);

        // Order matters: check "not available" cases before generic HTTP 5xx
        // because scraper errors can mention both "500" (wrapper) and "404" (cause).
        if (str_contains($lower, '404') || str_contains($lower, 'not found') || str_contains($lower, 'empty')) {
            return SyncStatus::REASON_EMPTY_RESPONSE;
        }
        if (str_contains($lower, '429') || str_contains($lower, 'rate limit')) {
            return SyncStatus::REASON_HTTP_429;
        }
        if (str_contains($lower, 'timeout') || str_contains($lower, 'timed out')) {
            return SyncStatus::REASON_TIMEOUT;
        }
        if (str_contains($lower, '500') || str_contains($lower, 'server error')) {
            return SyncStatus::REASON_HTTP_500;
        }

        return SyncStatus::REASON_NETWORK_ERROR;
    }

    private function nextRetryAt(int $retryCount): CarbonInterface
    {
        $schedule = config('appstorecat.sync.item_retry.backoff_seconds', [300, 900, 1800, 3600, 7200, 21600, 43200]);
        $index = min($retryCount - 1, count($schedule) - 1);

        return now()->addSeconds($schedule[max(0, $index)]);
    }

    private function pushFailedItem(SyncStatus $syncStatus, array $item): void
    {
        $syncStatus->refresh();
        $syncStatus->pushFailedItem($item);
        $syncStatus->save();
    }

    /**
     * Build a [language => preferred_country] map from the countries table.
     *
     * @return array<string, string>
     */
    public function iosLocaleMap(): array
    {
        return Cache::remember('ios_locale_map', 3600, function () {
            $countries = Country::where('is_active_ios', true)
                ->whereNotNull('ios_languages')
                ->orderByDesc('priority')
                ->get(['code', 'ios_languages']);

            $map = [];
            foreach ($countries as $c) {
                $langs = $c->ios_languages ?? [];
                $primary = $langs[0] ?? null;
                if ($primary && ! isset($map[$primary])) {
                    $map[$primary] = $c->code;
                }
            }
            foreach ($countries as $c) {
                foreach (($c->ios_languages ?? []) as $lang) {
                    if (! isset($map[$lang])) {
                        $map[$lang] = $c->code;
                    }
                }
            }

            return $map;
        });
    }

    /**
     * @return array<string, string>
     */
    public function androidLocaleMap(): array
    {
        return Cache::remember('android_locale_map', 3600, function () {
            $countries = Country::where('is_active_android', true)
                ->whereNotNull('android_languages')
                ->orderByDesc('priority')
                ->get(['code', 'android_languages']);

            $map = [];
            foreach ($countries as $c) {
                $langs = $c->android_languages ?? [];
                $primary = $langs[0] ?? null;
                if ($primary && ! isset($map[$primary])) {
                    $map[$primary] = $c->code;
                }
            }
            foreach ($countries as $c) {
                foreach (($c->android_languages ?? []) as $lang) {
                    if (! isset($map[$lang])) {
                        $map[$lang] = $c->code;
                    }
                }
            }

            return $map;
        });
    }

    /**
     * @return array<int, string>
     */
    public function iosActiveCountries(): array
    {
        return Cache::remember('ios_active_countries', 3600, fn () => Country::where('is_active_ios', true)
            ->orderByDesc('priority')
            ->pluck('code')
            ->all());
    }
}
