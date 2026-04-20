<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseController;
use App\Jobs\Chart\FetchChartSnapshotJob;
use App\Models\ChartEntry;
use App\Models\ChartSnapshot;
use App\Models\StoreCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class ChartController extends BaseController
{
    #[OA\Get(
        path: '/charts',
        summary: 'Get chart rankings',
        tags: ['Charts'],
        operationId: 'getCharts',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'platform', in: 'query', required: true, schema: new OA\Schema(type: 'string', enum: ['ios', 'android'])),
            new OA\Parameter(name: 'collection', in: 'query', required: true, schema: new OA\Schema(type: 'string', enum: ['top_free', 'top_paid', 'top_grossing'])),
            new OA\Parameter(name: 'country_code', in: 'query', required: false, schema: new OA\Schema(type: 'string', default: 'us')),
            new OA\Parameter(name: 'category_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Chart data'),
        ],
    )]
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'platform' => 'required|in:ios,android',
            'collection' => 'required|in:top_free,top_paid,top_grossing',
            'country_code' => 'sometimes|string|size:2|exists:countries,code',
            'category_id' => 'sometimes|integer|exists:store_categories,id',
        ]);

        $platform = $request->input('platform');
        $collection = $request->input('collection');
        $countryCode = $request->input('country_code', 'us');
        // Default to the platform's "All" category when no explicit filter is given.
        $categoryId = $request->input('category_id')
            ? (int) $request->input('category_id')
            : StoreCategory::platform($platform)->whereNull('external_id')->where('type', 'app')->value('id');

        $snapshot = ChartSnapshot::forChart($platform, $collection, $countryCode, $categoryId)
            ->orderByDesc('snapshot_date')
            ->orderByDesc('created_at')
            ->first();

        $isStale = ! $snapshot || ! $snapshot->snapshot_date->isToday();

        if ($isStale) {
            FetchChartSnapshotJob::dispatchSync($platform, $collection, $countryCode, $categoryId);

            $snapshot = ChartSnapshot::forChart($platform, $collection, $countryCode, $categoryId)
                ->orderByDesc('snapshot_date')
                ->orderByDesc('created_at')
                ->first();
        }

        if (! $snapshot) {
            return response()->json([
                'message' => 'No chart data available.',
                'entries' => [],
                'snapshot_date' => null,
            ]);
        }

        $entries = $snapshot->entries()->with(['app.publisher', 'app.category'])->get();

        $previousSnapshot = ChartSnapshot::forChart($platform, $collection, $countryCode, $categoryId)
            ->where('snapshot_date', '<', $snapshot->snapshot_date)
            ->orderByDesc('snapshot_date')
            ->first();

        $previousRanks = [];
        if ($previousSnapshot) {
            $previousRanks = ChartEntry::where('trending_chart_id', $previousSnapshot->id)
                ->pluck('rank', 'app_id')
                ->toArray();
        }

        $data = $entries->map(function (ChartEntry $entry) use ($previousRanks) {
            $prevRank = $previousRanks[$entry->app_id] ?? null;
            $rankChange = $prevRank !== null ? $prevRank - $entry->rank : null;
            $app = $entry->app;

            return [
                'rank' => $entry->rank,
                'rank_change' => $rankChange,
                'app_id' => $app->id,
                'app_external_id' => $app->external_id,
                'app_name' => $app->displayName(),
                'icon_url' => $app->displayIcon(),
                'platform' => $app->platform,
                'publisher' => $app->publisher ? [
                    'id' => $app->publisher->id,
                    'name' => $app->publisher->name,
                ] : null,
                'category_name' => $app->category?->name,
                'price' => (float) $entry->price,
                'currency' => $entry->currency,
                'is_free' => $entry->price == 0,
            ];
        });

        return response()->json([
            'snapshot_date' => $snapshot->snapshot_date->toDateString(),
            'updated_at' => $snapshot->created_at->toIso8601String(),
            'platform' => $platform,
            'collection' => $collection,
            'country_code' => $countryCode,
            'entries' => $data,
        ]);
    }
}
