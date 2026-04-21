<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\App;

use App\Http\Resources\Api\BaseResource;
use App\Models\AppMetric;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/** @mixin AppMetric */
#[OA\Schema(
    schema: 'RatingHistoryPointResource',
    required: ['month', 'rating', 'rating_count'],
    properties: [
        new OA\Property(property: 'month', type: 'string', example: '2026-03'),
        new OA\Property(property: 'rating', type: 'number', format: 'float', example: 4.08),
        new OA\Property(property: 'rating_count', type: 'integer', example: 1250),
    ],
)]
class RatingHistoryPointResource extends BaseResource
{
    protected function getResourceData(Request $request): array
    {
        /** @var AppMetric $metric */
        $metric = $this->resource;

        return [
            'month' => $metric->date->format('Y-m'),
            'rating' => (float) $metric->rating,
            'rating_count' => (int) $metric->rating_count,
        ];
    }

    protected function getDefaultMessage(): string
    {
        return 'Rating history retrieved successfully';
    }
}
