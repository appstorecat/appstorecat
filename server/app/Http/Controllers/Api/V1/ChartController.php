<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\Chart\ChartIndexRequest;
use App\Http\Resources\Api\Chart\ChartEntryResource;
use App\Jobs\Chart\FetchChartSnapshotJob;
use App\Models\ChartEntry;
use App\Models\ChartSnapshot;
use App\Models\StoreCategory;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
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
            new OA\Response(
                response: 200,
                description: 'Chart data',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/ChartEntryResource')),
                        new OA\Property(property: 'meta', type: 'object', properties: [
                            new OA\Property(property: 'snapshot_date', type: 'string', format: 'date', nullable: true),
                            new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', nullable: true),
                            new OA\Property(property: 'platform', type: 'string'),
                            new OA\Property(property: 'collection', type: 'string'),
                            new OA\Property(property: 'country_code', type: 'string'),
                            new OA\Property(property: 'message', type: 'string', nullable: true),
                        ]),
                    ],
                ),
            ),
        ],
    )]
    public function index(ChartIndexRequest $request): AnonymousResourceCollection
    {
        $platform = $request->validated('platform');
        $collection = $request->validated('collection');
        $countryCode = $request->validated('country_code') ?? 'us';
        // Default to the platform's "All" category when no explicit filter is given.
        $categoryId = $request->validated('category_id')
            ? (int) $request->validated('category_id')
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
            return ChartEntryResource::collection([])->additional([
                'meta' => [
                    'message' => 'No chart data available.',
                    'snapshot_date' => null,
                    'updated_at' => null,
                    'platform' => $platform,
                    'collection' => $collection,
                    'country_code' => $countryCode,
                ],
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

        // Attach `previous_rank` as a dynamic property so the resource can
        // compute `rank_change` without a second DB pass per row.
        $entries->each(function (ChartEntry $entry) use ($previousRanks) {
            $entry->previous_rank = $previousRanks[$entry->app_id] ?? null;
        });

        return ChartEntryResource::collection($entries)->additional([
            'meta' => [
                'snapshot_date' => $snapshot->snapshot_date->toDateString(),
                'updated_at' => $snapshot->created_at->toIso8601String(),
                'platform' => $platform,
                'collection' => $collection,
                'country_code' => $countryCode,
            ],
        ]);
    }
}
