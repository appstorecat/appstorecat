<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\App;

use App\Http\Resources\Api\BaseResource;
use App\Models\App;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/** @mixin App */
#[OA\Schema(
    schema: 'AppDetailResource',
    required: ['id', 'name', 'platform', 'external_id', 'created_at'],
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'Instagram'),
        new OA\Property(property: 'platform', ref: '#/components/schemas/Platform'),
        new OA\Property(property: 'external_id', type: 'string', example: '389801252'),
        new OA\Property(property: 'publisher', ref: '#/components/schemas/Publisher', nullable: true),
        new OA\Property(property: 'category', ref: '#/components/schemas/StoreCategory', nullable: true),
        new OA\Property(property: 'supported_locales', type: 'array', items: new OA\Items(type: 'string'), nullable: true, example: ['EN', 'DE', 'FR']),
        new OA\Property(property: 'original_release_date', type: 'string', format: 'date', nullable: true, example: '2010-10-06'),
        new OA\Property(property: 'is_free', type: 'boolean', example: true),
        new OA\Property(property: 'icon_url', type: 'string', nullable: true),
        new OA\Property(property: 'rating', type: 'number', format: 'float', nullable: true, example: 4.68),
        new OA\Property(property: 'rating_count', type: 'integer', nullable: true, example: 31),
        new OA\Property(property: 'version', type: 'string', nullable: true, example: '1.2.4'),
        new OA\Property(property: 'file_size_bytes', type: 'integer', nullable: true, example: 73822208),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'listings', type: 'array', items: new OA\Items(ref: '#/components/schemas/ListingResource')),
        new OA\Property(property: 'versions', type: 'array', items: new OA\Items(ref: '#/components/schemas/VersionResource')),
        new OA\Property(property: 'changes', type: 'array', items: new OA\Items(ref: '#/components/schemas/StoreListingChange')),
        new OA\Property(property: 'competitors', type: 'array', items: new OA\Items(ref: '#/components/schemas/CompetitorResource')),
    ],
)]
class AppDetailResource extends BaseResource
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
                'url' => $this->resource->publisher->url,
            ] : null,
            'category' => $this->resource->category ? [
                'id' => $this->resource->category->id,
                'name' => $this->resource->category->name,
                'slug' => $this->resource->category->slug,
            ] : null,
            'supported_locales' => $this->resource->supported_locales,
            'original_release_date' => $this->resource->original_release_date?->toDateString(),
            'is_free' => $this->resource->is_free,
            'icon_url' => $this->resource->displayIcon(),
            'rating' => $latestMetric?->rating ? (float) $latestMetric->rating : null,
            'rating_count' => $latestMetric?->rating_count,
            'version' => $latestVersion?->version,
            'file_size_bytes' => $latestMetric?->file_size_bytes,
            'created_at' => $this->formatTimestamp($this->resource->created_at),
            'listings' => ListingResource::collection($this->whenLoaded('storeListings')),
            'versions' => VersionResource::collection($this->whenLoaded('versions')),
            'changes' => $this->whenLoaded('storeListingChanges', fn () => $this->resource->storeListingChanges->map(fn ($c) => [
                'id' => $c->id,
                'version_id' => $c->version_id,
                'locale' => $c->locale,
                'field_changed' => $c->field_changed,
                'old_value' => $c->field_changed === 'screenshots' ? null : $c->old_value,
                'new_value' => $c->field_changed === 'screenshots' ? null : $c->new_value,
                'detected_at' => $c->detected_at?->toIso8601String(),
            ])),
            'competitors' => CompetitorResource::collection($this->whenLoaded('competitors')),
            'is_available' => $this->resource->is_available,
            'is_tracked' => $request->user() ? $this->resource->isTrackedBy($request->user()) : false,
        ];
    }

    protected function getDefaultMessage(): string
    {
        return 'App details retrieved successfully';
    }
}
