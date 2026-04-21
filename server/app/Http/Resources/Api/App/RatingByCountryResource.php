<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\App;

use App\Http\Resources\Api\BaseResource;
use App\Models\AppMetric;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/** @mixin AppMetric */
#[OA\Schema(
    schema: 'RatingByCountryResource',
    required: ['country_code', 'rating', 'rating_count'],
    properties: [
        new OA\Property(property: 'country_code', type: 'string', example: 'us'),
        new OA\Property(property: 'rating', type: 'number', format: 'float', example: 4.2),
        new OA\Property(property: 'rating_count', type: 'integer', example: 36),
    ],
)]
class RatingByCountryResource extends BaseResource
{
    protected function getResourceData(Request $request): array
    {
        /** @var AppMetric $metric */
        $metric = $this->resource;

        return [
            'country_code' => $metric->country_code,
            'rating' => (float) $metric->rating,
            'rating_count' => (int) $metric->rating_count,
        ];
    }

    protected function getDefaultMessage(): string
    {
        return 'Country breakdown retrieved successfully';
    }
}
