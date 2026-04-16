<?php

declare(strict_types=1);

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'DashboardResource',
    properties: [
        new OA\Property(property: 'total_apps', type: 'integer', example: 5),
        new OA\Property(property: 'total_reviews', type: 'integer', example: 143),
        new OA\Property(property: 'total_versions', type: 'integer', example: 12),
        new OA\Property(property: 'total_changes', type: 'integer', example: 8),
        new OA\Property(property: 'recent_reviews', type: 'array', items: new OA\Items(type: 'object')),
        new OA\Property(property: 'recent_changes', type: 'array', items: new OA\Items(type: 'object')),
    ],
)]
class DashboardResource extends BaseResource
{
    protected function getResourceData(Request $request): array
    {
        return $this->resource;
    }

    protected function getDefaultMessage(): string
    {
        return 'Dashboard retrieved successfully';
    }
}
