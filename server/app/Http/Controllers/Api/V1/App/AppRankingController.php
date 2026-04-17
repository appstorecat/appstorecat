<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\App;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\App\AppRankingIndexRequest;
use App\Http\Resources\Api\App\AppRankingResource;
use App\Models\ChartEntry;
use App\Models\ChartSnapshot;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Attributes as OA;

class AppRankingController extends BaseController
{
    #[OA\Get(
        path: '/apps/{platform}/{externalId}/rankings',
        summary: 'List chart rankings for an app on a given date',
        tags: ['Apps'],
        operationId: 'listAppRankings',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'platform', in: 'path', required: true, schema: new OA\Schema(type: 'string', enum: ['ios', 'android'])),
            new OA\Parameter(name: 'externalId', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'date', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Rankings on the selected date', content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/AppRankingResource'))),
            new OA\Response(response: 404, description: 'App not found'),
        ],
    )]
    public function index(AppRankingIndexRequest $request, string $platform, string $externalId): AnonymousResourceCollection
    {
        $app = $this->resolveApp($platform, $externalId);
        $selectedDate = $request->input('date') ?? now()->toDateString();

        $entries = ChartEntry::query()
            ->select('trending_chart_entries.*')
            ->join('trending_charts', 'trending_charts.id', '=', 'trending_chart_entries.trending_chart_id')
            ->where('trending_chart_entries.app_id', $app->id)
            ->where('trending_charts.platform', $platform)
            ->where('trending_charts.snapshot_date', $selectedDate)
            ->with(['snapshot.category'])
            ->get();

        $rows = $entries->map(function (ChartEntry $entry) use ($app) {
            $snapshot = $entry->snapshot;

            $previous = ChartSnapshot::forChart(
                $snapshot->platform->value,
                $snapshot->collection->value,
                $snapshot->country,
                $snapshot->category_id,
            )
                ->where('snapshot_date', '<', $snapshot->snapshot_date)
                ->orderByDesc('snapshot_date')
                ->first();

            $previousRank = $previous
                ? ChartEntry::where('trending_chart_id', $previous->id)
                    ->where('app_id', $app->id)
                    ->value('rank')
                : null;

            $status = match (true) {
                $previousRank === null => 'new',
                $previousRank > $entry->rank => 'up',
                $previousRank < $entry->rank => 'down',
                default => 'same',
            };

            return [
                'country' => $snapshot->country,
                'collection' => $snapshot->collection->value,
                'category' => $snapshot->category ? [
                    'id' => $snapshot->category->id,
                    'name' => $snapshot->category->name,
                ] : null,
                'rank' => (int) $entry->rank,
                'previous_rank' => $previousRank !== null ? (int) $previousRank : null,
                'status' => $status,
                'snapshot_date' => $snapshot->snapshot_date->toDateString(),
            ];
        })
            ->sortBy([
                ['country', 'asc'],
                ['collection', 'asc'],
                ['rank', 'asc'],
            ])
            ->values();

        return AppRankingResource::collection($rows);
    }
}
