<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\App;

use App\Http\Resources\Api\BaseResource;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'ReviewSummaryResource',
    properties: [
        new OA\Property(property: 'total_reviews', type: 'integer', example: 150),
        new OA\Property(property: 'average_rating', type: 'number', format: 'float', example: 4.2),
        new OA\Property(property: 'distribution', type: 'object'),
        new OA\Property(property: 'countries', type: 'array', items: new OA\Items(type: 'string')),
    ],
)]
class ReviewSummaryResource extends BaseResource
{
    protected function getResourceData(Request $request): array
    {
        return $this->resource;
    }

    protected function getDefaultMessage(): string
    {
        return 'Review summary retrieved successfully';
    }
}
