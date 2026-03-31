<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\Publisher;

use App\Http\Resources\Api\BaseResource;
use App\Models\Publisher;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/** @mixin Publisher */
#[OA\Schema(
    schema: 'PublisherResource',
    allOf: [new OA\Schema(ref: '#/components/schemas/Publisher')],
    properties: [
        new OA\Property(property: 'apps_count', type: 'integer', nullable: true, example: 5),
    ],
)]
class PublisherResource extends BaseResource
{
    protected function getResourceData(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'external_id' => $this->resource->external_id,
            'platform' => $this->resource->platform,
            'url' => $this->resource->url,
            'apps_count' => $this->resource->apps_count ?? null,
        ];
    }

    protected function getDefaultMessage(): string
    {
        return 'Publisher retrieved successfully';
    }
}
