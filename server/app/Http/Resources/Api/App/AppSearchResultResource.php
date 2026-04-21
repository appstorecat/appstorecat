<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\App;

use App\Http\Resources\Api\BaseResource;
use App\Models\App;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/** @mixin App */
#[OA\Schema(
    schema: 'AppSearchResultResource',
    required: ['id', 'platform', 'external_id', 'name'],
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'platform', type: 'string', enum: ['ios', 'android']),
        new OA\Property(property: 'external_id', type: 'string', example: '389801252'),
        new OA\Property(property: 'name', type: 'string', example: 'Instagram'),
        new OA\Property(property: 'icon_url', type: 'string', nullable: true),
        new OA\Property(property: 'publisher_name', type: 'string', nullable: true, example: 'Instagram, Inc.'),
        new OA\Property(property: 'rating', type: 'number', format: 'float', nullable: true, example: 4.68),
        new OA\Property(property: 'rating_count', type: 'integer', nullable: true, example: 31),
        new OA\Property(property: 'version', type: 'string', nullable: true, example: '1.2.4'),
        new OA\Property(property: 'is_available', type: 'boolean', nullable: true, example: true),
        new OA\Property(property: 'is_tracked', type: 'boolean', nullable: true, example: false),
        new OA\Property(
            property: 'publisher',
            type: 'object',
            nullable: true,
            properties: [
                new OA\Property(property: 'id', type: 'integer'),
                new OA\Property(property: 'name', type: 'string'),
                new OA\Property(property: 'external_id', type: 'string', nullable: true),
                new OA\Property(property: 'platform', type: 'string', enum: ['ios', 'android']),
            ],
        ),
        new OA\Property(
            property: 'category',
            type: 'object',
            nullable: true,
            properties: [
                new OA\Property(property: 'id', type: 'integer'),
                new OA\Property(property: 'name', type: 'string'),
                new OA\Property(property: 'slug', type: 'string'),
            ],
        ),
    ],
)]
class AppSearchResultResource extends BaseResource
{
    protected function getResourceData(Request $request): array
    {
        $app = $this->resource;
        $latestMetric = $app->metrics()->orderByDesc('date')->first();
        $latestVersion = $app->versions()->latest()->first();

        return [
            'id' => $app->id,
            'platform' => $app->platform,
            'external_id' => $app->external_id,
            'name' => $app->displayName(),
            'icon_url' => $app->displayIcon(),
            'publisher_name' => $app->publisher?->name,
            'rating' => $latestMetric?->rating ? (float) $latestMetric->rating : null,
            'rating_count' => $latestMetric?->rating_count,
            'version' => $latestVersion?->version,
            'is_available' => $app->is_available,
            'is_tracked' => $request->user() ? $app->isTrackedBy($request->user()) : false,
            'publisher' => $app->publisher ? [
                'id' => $app->publisher->id,
                'name' => $app->publisher->name,
                'external_id' => $app->publisher->external_id,
                'platform' => $app->publisher->platform,
            ] : null,
            'category' => $app->category ? [
                'id' => $app->category->id,
                'name' => $app->category->name,
                'slug' => $app->category->slug,
            ] : null,
        ];
    }

    protected function getDefaultMessage(): string
    {
        return 'App search results retrieved successfully';
    }
}
