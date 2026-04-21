<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\StoreCategory;

use App\Http\Resources\Api\BaseResource;
use App\Models\StoreCategory;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/** @mixin StoreCategory */
#[OA\Schema(
    schema: 'StoreCategoryResource',
    allOf: [new OA\Schema(ref: '#/components/schemas/StoreCategory')],
    properties: [
        new OA\Property(property: 'parent_id', type: 'integer', nullable: true),
        new OA\Property(property: 'type', type: 'string', nullable: true),
        new OA\Property(property: 'external_id', type: 'string', nullable: true),
    ],
)]
class StoreCategoryResource extends BaseResource
{
    protected function getResourceData(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'slug' => $this->resource->slug,
            'platform' => $this->resource->platform,
            'type' => $this->resource->type,
            'external_id' => $this->resource->external_id,
            'parent_id' => $this->resource->parent_id,
        ];
    }

    protected function getDefaultMessage(): string
    {
        return 'Store category retrieved successfully';
    }
}
