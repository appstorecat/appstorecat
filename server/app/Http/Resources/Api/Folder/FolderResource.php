<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\Folder;

use App\Http\Resources\Api\BaseResource;
use App\Models\Folder;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/** @mixin Folder */
#[OA\Schema(
    schema: 'FolderResource',
    allOf: [new OA\Schema(ref: '#/components/schemas/Folder')],
    required: ['apps_count'],
    properties: [
        new OA\Property(property: 'apps_count', type: 'integer', example: 3),
    ],
)]
class FolderResource extends BaseResource
{
    protected function getResourceData(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'color' => $this->resource->color->value,
            'sort_order' => $this->resource->sort_order,
            'apps_count' => (int) ($this->resource->apps_count ?? 0),
            'created_at' => $this->formatTimestamp($this->resource->created_at),
            'updated_at' => $this->formatTimestamp($this->resource->updated_at),
        ];
    }

    protected function getDefaultMessage(): string
    {
        return 'Folder retrieved successfully';
    }
}
