<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\Publisher;

use App\Http\Resources\Api\App\AppResource;
use App\Http\Resources\Api\BaseResource;
use App\Models\Publisher;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/** @mixin Publisher */
#[OA\Schema(
    schema: 'PublisherDetailResource',
    properties: [
        new OA\Property(property: 'publisher', ref: '#/components/schemas/PublisherResource'),
        new OA\Property(property: 'apps', type: 'array', items: new OA\Items(ref: '#/components/schemas/AppResource')),
    ],
)]
class PublisherDetailResource extends BaseResource
{
    protected function getResourceData(Request $request): array
    {
        return [
            'publisher' => [
                'id' => $this->resource->id,
                'name' => $this->resource->name,
                'external_id' => $this->resource->external_id,
                'platform' => $this->resource->platform,
                'url' => $this->resource->url,
            ],
            'apps' => AppResource::collection($this->resource->relationLoaded('trackedApps')
                ? $this->resource->trackedApps
                : collect()),
        ];
    }

    protected function getDefaultMessage(): string
    {
        return 'Publisher details retrieved successfully';
    }
}
