<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\Publisher;

use App\Http\Resources\Api\BaseResource;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'StoreAppResource',
    required: ['external_id', 'name'],
    properties: [
        new OA\Property(property: 'external_id', type: 'string', example: '389801252'),
        new OA\Property(property: 'name', type: 'string', example: 'Instagram'),
        new OA\Property(property: 'icon_url', type: 'string', nullable: true),
        new OA\Property(property: 'rating', type: 'number', format: 'float', nullable: true),
        new OA\Property(property: 'rating_count', type: 'integer', nullable: true),
        new OA\Property(property: 'is_free', type: 'boolean'),
        new OA\Property(property: 'category', type: 'string', nullable: true),
        new OA\Property(property: 'is_tracked', type: 'boolean'),
    ],
)]
class StoreAppResource extends BaseResource
{
    protected function getResourceData(Request $request): array
    {
        return [
            'external_id' => $this->resource['external_id'],
            'name' => $this->resource['name'],
            'icon_url' => $this->resource['icon_url'] ?? null,
            'rating' => $this->resource['rating'] ?? null,
            'rating_count' => $this->resource['rating_count'] ?? null,
            'is_free' => $this->resource['is_free'] ?? true,
            'category' => $this->resource['category'] ?? null,
            'is_tracked' => $this->resource['is_tracked'] ?? false,
        ];
    }

    protected function getDefaultMessage(): string
    {
        return 'Store app retrieved successfully';
    }
}
