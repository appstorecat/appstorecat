<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\App;

use App\Http\Resources\Api\BaseResource;
use App\Models\ChartEntry;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Expects a ChartEntry with an eager-loaded `snapshot.category` relation and
 * a `previous_rank` dynamic property attached by the controller (nullable —
 * null when the app did not appear in the previous snapshot).
 *
 * @mixin ChartEntry
 */
#[OA\Schema(
    schema: 'AppRankingResource',
    properties: [
        new OA\Property(property: 'country_code', type: 'string'),
        new OA\Property(property: 'collection', type: 'string'),
        new OA\Property(property: 'category', type: 'object', nullable: true, properties: [
            new OA\Property(property: 'id', type: 'integer'),
            new OA\Property(property: 'name', type: 'string'),
        ]),
        new OA\Property(property: 'rank', type: 'integer'),
        new OA\Property(property: 'previous_rank', type: 'integer', nullable: true),
        new OA\Property(property: 'rank_change', type: 'integer', nullable: true),
        new OA\Property(property: 'status', type: 'string', enum: ['new', 'up', 'down', 'same']),
        new OA\Property(property: 'snapshot_date', type: 'string', format: 'date'),
    ],
)]
class AppRankingResource extends BaseResource
{
    protected function getResourceData(Request $request): array
    {
        /** @var ChartEntry $entry */
        $entry = $this->resource;
        $snapshot = $entry->snapshot;

        $previousRank = $entry->previous_rank ?? null;
        $previousRank = $previousRank !== null ? (int) $previousRank : null;
        $rank = (int) $entry->rank;

        $status = match (true) {
            $previousRank === null => 'new',
            $previousRank > $rank => 'up',
            $previousRank < $rank => 'down',
            default => 'same',
        };

        return [
            'country_code' => $snapshot->country_code,
            'collection' => $snapshot->collection->value,
            'category' => $snapshot->category ? [
                'id' => $snapshot->category->id,
                'name' => $snapshot->category->name,
            ] : null,
            'rank' => $rank,
            'previous_rank' => $previousRank,
            'rank_change' => $previousRank !== null ? $previousRank - $rank : null,
            'status' => $status,
            'snapshot_date' => $snapshot->snapshot_date->toDateString(),
        ];
    }

    protected function getDefaultMessage(): string
    {
        return 'Ranking retrieved successfully';
    }
}
