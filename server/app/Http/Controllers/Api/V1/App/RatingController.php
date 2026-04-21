<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\App;

use App\Enums\Platform;
use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\App\RatingCountryBreakdownRequest;
use App\Http\Requests\Api\App\RatingHistoryRequest;
use App\Http\Requests\Api\App\RatingSummaryRequest;
use App\Http\Resources\Api\App\RatingByCountryResource;
use App\Http\Resources\Api\App\RatingHistoryPointResource;
use App\Http\Resources\Api\App\RatingSummaryResource;
use App\Models\App;
use App\Models\AppMetric;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use OpenApi\Attributes as OA;

class RatingController extends BaseController
{
    #[OA\Get(
        path: '/apps/{platform}/{externalId}/ratings/summary',
        summary: 'Get the rating summary (current rating + 30-day trend)',
        tags: ['Apps'],
        operationId: 'getRatingSummary',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'platform', in: 'path', required: true, schema: new OA\Schema(type: 'string', enum: ['ios', 'android'])),
            new OA\Parameter(name: 'externalId', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Rating summary', content: new OA\JsonContent(ref: '#/components/schemas/RatingSummaryResource')),
            new OA\Response(response: 404, description: 'App not found'),
        ],
    )]
    public function summary(RatingSummaryRequest $request, string $platform, string $externalId): RatingSummaryResource
    {
        $app = $this->resolveApp($platform, $externalId);

        $latestDate = AppMetric::query()
            ->where('app_id', $app->id)
            ->max('date');

        $latest = $latestDate
            ? $this->aggregateSnapshot($app, $platform, $latestDate)
            : null;

        $baseline = null;
        if ($latest !== null) {
            $baselineDate = AppMetric::query()
                ->where('app_id', $app->id)
                ->where('date', '<=', Carbon::parse($latestDate)->subDays(30))
                ->max('date');

            if ($baselineDate) {
                $baseline = $this->aggregateSnapshot($app, $platform, $baselineDate);
            }
        }

        return new RatingSummaryResource([
            'latest' => $latest,
            'baseline' => $baseline,
        ]);
    }

    #[OA\Get(
        path: '/apps/{platform}/{externalId}/ratings/history',
        summary: 'Get monthly rating history (last N months)',
        tags: ['Apps'],
        operationId: 'getRatingHistory',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'platform', in: 'path', required: true, schema: new OA\Schema(type: 'string', enum: ['ios', 'android'])),
            new OA\Parameter(name: 'externalId', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'months', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 24, default: 12)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Monthly history points', content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/RatingHistoryPointResource'))),
            new OA\Response(response: 404, description: 'App not found'),
        ],
    )]
    public function history(RatingHistoryRequest $request, string $platform, string $externalId): AnonymousResourceCollection
    {
        $app = $this->resolveApp($platform, $externalId);
        $months = $request->months();

        $startDate = now()->startOfMonth()->subMonths($months - 1)->toDateString();

        // For each calendar month, use the last date that has data and
        // aggregate all countries on that date into a single global snapshot.
        $distinctDates = AppMetric::query()
            ->where('app_id', $app->id)
            ->where('date', '>=', $startDate)
            ->orderBy('date')
            ->pluck('date')
            ->unique()
            ->values();

        $lastDateByMonth = [];
        foreach ($distinctDates as $date) {
            $key = $date->format('Y-m');
            $lastDateByMonth[$key] = $date;
        }

        $points = collect($lastDateByMonth)
            ->sortKeys()
            ->map(fn ($date) => $this->aggregateSnapshot($app, $platform, $date->toDateString()))
            ->filter()
            ->values();

        return RatingHistoryPointResource::collection($points);
    }

    #[OA\Get(
        path: '/apps/{platform}/{externalId}/ratings/country-breakdown',
        summary: 'Get the latest rating snapshot per country (iOS only)',
        tags: ['Apps'],
        operationId: 'getRatingCountryBreakdown',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'platform', in: 'path', required: true, schema: new OA\Schema(type: 'string', enum: ['ios', 'android'])),
            new OA\Parameter(name: 'externalId', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Per-country rating snapshots', content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/RatingByCountryResource'))),
            new OA\Response(response: 404, description: 'App not found'),
        ],
    )]
    public function countryBreakdown(
        RatingCountryBreakdownRequest $request,
        string $platform,
        string $externalId,
    ): AnonymousResourceCollection {
        $app = $this->resolveApp($platform, $externalId);

        if (Platform::fromSlug($platform) === Platform::Android) {
            return RatingByCountryResource::collection([])
                ->additional([
                    'supported' => false,
                    'message' => 'Google Play does not provide ratings data by country.',
                ]);
        }

        $rows = $this->latestMetricPerCountry($app);

        return RatingByCountryResource::collection($rows)
            ->additional(['supported' => true]);
    }

    /**
     * Build a global rating snapshot for the given date. Android only ever
     * stores the `zz` row so we pass that through. iOS stores one row per
     * country — we sum `rating_count`, compute a rating-count-weighted
     * average for `rating`, and add breakdowns bucket-by-bucket.
     *
     * Returns an AppMetric-shaped object (actual instance for Android, a
     * synthetic fill for iOS) so the resource layer can stay uniform.
     */
    private function aggregateSnapshot(App $app, string $platform, string $date): ?AppMetric
    {
        if (Platform::fromSlug($platform) === Platform::Android) {
            return AppMetric::query()
                ->where('app_id', $app->id)
                ->where('country_code', AppMetric::GLOBAL_COUNTRY)
                ->where('date', $date)
                ->first();
        }

        $rows = AppMetric::query()
            ->where('app_id', $app->id)
            ->where('date', $date)
            ->where('rating_count', '>', 0)
            ->get();

        if ($rows->isEmpty()) {
            return null;
        }

        $totalCount = (int) $rows->sum('rating_count');
        $weightedRating = $totalCount > 0
            ? $rows->sum(fn (AppMetric $m) => (float) $m->rating * (int) $m->rating_count) / $totalCount
            : 0.0;

        $breakdown = ['1' => 0, '2' => 0, '3' => 0, '4' => 0, '5' => 0];
        foreach ($rows as $row) {
            foreach (['1', '2', '3', '4', '5'] as $star) {
                $breakdown[$star] += (int) ($row->rating_breakdown[$star] ?? 0);
            }
        }

        $snapshot = new AppMetric;
        $snapshot->app_id = $app->id;
        $snapshot->date = $date;
        $snapshot->rating = round($weightedRating, 2);
        $snapshot->rating_count = $totalCount;
        $snapshot->rating_breakdown = $breakdown;

        return $snapshot;
    }

    /**
     * Return the most recent AppMetric row for each country for the given app.
     *
     * @return Collection<int, AppMetric>
     */
    private function latestMetricPerCountry(App $app): Collection
    {
        return AppMetric::query()
            ->where('app_id', $app->id)
            ->orderBy('country_code')
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->get()
            ->groupBy('country_code')
            ->map(fn ($group) => $group->first())
            ->values();
    }
}
