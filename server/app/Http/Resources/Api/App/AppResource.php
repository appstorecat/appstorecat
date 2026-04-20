<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\App;

use App\Http\Resources\Api\BaseResource;
use App\Models\App;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/** @mixin App */
#[OA\Schema(
    schema: 'AppResource',
    allOf: [new OA\Schema(ref: '#/components/schemas/App')],
    required: ['name'],
    properties: [
        new OA\Property(property: 'name', type: 'string', example: 'Instagram'),
        new OA\Property(property: 'rating', type: 'number', format: 'float', nullable: true, example: 4.68),
        new OA\Property(property: 'rating_count', type: 'integer', nullable: true, example: 31),
        new OA\Property(property: 'version', type: 'string', nullable: true, example: '1.2.4'),
        new OA\Property(property: 'is_tracked', type: 'boolean', example: false),
    ],
)]
class AppResource extends BaseResource
{
    protected function getResourceData(Request $request): array
    {
        $latestMetric = $this->resource->metrics()->orderByDesc('date')->first();
        $latestVersion = $this->resource->versions()->latest()->first();

        return [
            'id' => $this->resource->id,
            'name' => $this->resource->displayName(),
            'platform' => $this->resource->platform,
            'external_id' => $this->resource->external_id,
            'publisher' => $this->resource->publisher ? [
                'id' => $this->resource->publisher->id,
                'name' => $this->resource->publisher->name,
                'external_id' => $this->resource->publisher->external_id,
                'platform' => $this->resource->publisher->platform,
            ] : null,
            'category' => $this->resource->category ? [
                'id' => $this->resource->category->id,
                'name' => $this->resource->category->name,
                'slug' => $this->resource->category->slug,
            ] : null,
            'icon_url' => $this->resource->displayIcon(),
            'rating' => $latestMetric?->rating ? (float) $latestMetric->rating : null,
            'rating_count' => $latestMetric?->rating_count,
            'version' => $latestVersion?->version,
            'created_at' => $this->formatTimestamp($this->resource->created_at),
            'is_available' => $this->resource->is_available,
            'is_tracked' => $request->user() ? $this->resource->isTrackedBy($request->user()) : false,
        ];
    }

    protected function getDefaultMessage(): string
    {
        return 'App retrieved successfully';
    }
}
