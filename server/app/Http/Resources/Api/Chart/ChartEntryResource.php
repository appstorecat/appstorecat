<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\Chart;

use App\Http\Resources\Api\BaseResource;
use App\Models\ChartEntry;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/** @mixin ChartEntry */
#[OA\Schema(
    schema: 'ChartEntryResource',
    properties: [
        new OA\Property(property: 'rank', type: 'integer'),
        new OA\Property(property: 'rank_change', type: 'integer', nullable: true),
        new OA\Property(property: 'app_id', type: 'integer'),
        new OA\Property(property: 'app_external_id', type: 'string'),
        new OA\Property(property: 'app_name', type: 'string'),
        new OA\Property(property: 'icon_url', type: 'string', nullable: true),
        new OA\Property(property: 'platform', ref: '#/components/schemas/Platform'),
        new OA\Property(property: 'publisher', type: 'object', nullable: true, properties: [
            new OA\Property(property: 'id', type: 'integer'),
            new OA\Property(property: 'name', type: 'string'),
        ]),
        new OA\Property(property: 'category_name', type: 'string', nullable: true),
        new OA\Property(property: 'price', type: 'number', format: 'float'),
        new OA\Property(property: 'currency', type: 'string', nullable: true),
        new OA\Property(property: 'is_free', type: 'boolean'),
    ],
)]
class ChartEntryResource extends BaseResource
{
    protected function getResourceData(Request $request): array
    {
        /** @var ChartEntry $entry */
        $entry = $this->resource;
        $app = $entry->app;

        // Controllers may attach `previous_rank` directly on the model
        // (dynamic property) so the resource can compute `rank_change`
        // without re-querying. Falls back to null when unavailable.
        $prevRank = $entry->previous_rank ?? null;
        $rankChange = $prevRank !== null ? (int) $prevRank - (int) $entry->rank : null;

        return [
            'rank' => (int) $entry->rank,
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
            'is_free' => (float) $entry->price === 0.0,
        ];
    }

    protected function getDefaultMessage(): string
    {
        return 'Chart entry retrieved successfully';
    }
}
