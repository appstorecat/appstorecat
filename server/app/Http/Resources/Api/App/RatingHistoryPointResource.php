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
    required: ['date', 'rating', 'rating_count', 'breakdown'],
    properties: [
        new OA\Property(property: 'date', type: 'string', format: 'date', example: '2026-03-15'),
        new OA\Property(property: 'rating', type: 'number', format: 'float', nullable: true, example: 4.08),
        new OA\Property(property: 'rating_count', type: 'integer', nullable: true, example: 1250),
        new OA\Property(property: 'breakdown', type: 'object', properties: [
            new OA\Property(property: '1', type: 'integer', example: 12),
            new OA\Property(property: '2', type: 'integer', example: 8),
            new OA\Property(property: '3', type: 'integer', example: 42),
            new OA\Property(property: '4', type: 'integer', example: 120),
            new OA\Property(property: '5', type: 'integer', example: 1068),
        ]),
        new OA\Property(property: 'delta_breakdown', type: 'object', nullable: true, properties: [
            new OA\Property(property: '1', type: 'integer', example: 0),
            new OA\Property(property: '2', type: 'integer', example: 1),
            new OA\Property(property: '3', type: 'integer', example: 4),
            new OA\Property(property: '4', type: 'integer', example: 12),
            new OA\Property(property: '5', type: 'integer', example: 138),
        ]),
        new OA\Property(property: 'delta_total', type: 'integer', nullable: true, example: 155),
    ],
)]
class RatingHistoryPointResource extends BaseResource
{
    protected function getResourceData(Request $request): array
    {
        /** @var AppMetric $metric */
        $metric = $this->resource;

        $date = $metric->date;
        $dateStr = is_string($date) ? $date : $date->format('Y-m-d');

        $breakdown = ['1' => 0, '2' => 0, '3' => 0, '4' => 0, '5' => 0];
        foreach (['1', '2', '3', '4', '5'] as $star) {
            $breakdown[$star] = (int) ($metric->rating_breakdown[$star] ?? 0);
        }

        $deltaRaw = $metric->getAttribute('delta_breakdown');
        $delta = null;
        if (is_array($deltaRaw)) {
            $delta = ['1' => 0, '2' => 0, '3' => 0, '4' => 0, '5' => 0];
            foreach (['1', '2', '3', '4', '5'] as $star) {
                $delta[$star] = (int) ($deltaRaw[$star] ?? 0);
            }
        }

        $deltaTotal = $metric->getAttribute('delta_total');

        return [
            'date' => $dateStr,
            'rating' => $metric->rating === null ? null : (float) $metric->rating,
            'rating_count' => $metric->rating_count === null ? null : (int) $metric->rating_count,
            'breakdown' => (object) $breakdown,
            'delta_breakdown' => $delta === null ? null : (object) $delta,
            'delta_total' => $deltaTotal === null ? null : (int) $deltaTotal,
        ];
    }

    protected function getDefaultMessage(): string
    {
        return 'Rating history retrieved successfully';
    }
}
