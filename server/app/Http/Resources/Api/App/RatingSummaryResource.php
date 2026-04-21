<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\App;

use App\Http\Resources\Api\BaseResource;
use App\Models\AppMetric;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Expects the underlying resource to be an associative array of the form:
 *   ['latest' => ?AppMetric, 'baseline' => ?AppMetric]
 *
 * `latest` is the most recent metric row for the relevant country.
 * `baseline` is the metric row used as the 30-day comparison anchor (or null
 * when no such row exists).
 */
#[OA\Schema(
    schema: 'RatingSummaryResource',
    required: ['rating', 'rating_count', 'breakdown', 'trend'],
    properties: [
        new OA\Property(property: 'rating', type: 'number', format: 'float', example: 4.12),
        new OA\Property(property: 'rating_count', type: 'integer', example: 139),
        new OA\Property(
            property: 'breakdown',
            type: 'object',
            nullable: true,
            properties: [
                new OA\Property(property: '1', type: 'integer'),
                new OA\Property(property: '2', type: 'integer'),
                new OA\Property(property: '3', type: 'integer'),
                new OA\Property(property: '4', type: 'integer'),
                new OA\Property(property: '5', type: 'integer'),
            ],
        ),
        new OA\Property(
            property: 'trend',
            type: 'object',
            required: ['rating_delta_30d', 'rating_count_delta_30d'],
            properties: [
                new OA\Property(property: 'rating_delta_30d', type: 'number', format: 'float', nullable: true),
                new OA\Property(property: 'rating_count_delta_30d', type: 'integer', nullable: true),
            ],
        ),
    ],
)]
class RatingSummaryResource extends BaseResource
{
    protected function getResourceData(Request $request): array
    {
        /** @var array{latest: ?AppMetric, baseline: ?AppMetric} $data */
        $data = $this->resource;

        $latest = $data['latest'] ?? null;
        $baseline = $data['baseline'] ?? null;

        $rating = $latest ? (float) $latest->rating : 0.0;
        $ratingCount = $latest ? (int) $latest->rating_count : 0;

        $ratingDelta = null;
        $ratingCountDelta = null;

        if ($latest !== null && $baseline !== null) {
            $ratingDelta = round((float) $latest->rating - (float) $baseline->rating, 2);
            $ratingCountDelta = (int) $latest->rating_count - (int) $baseline->rating_count;
        }

        return [
            'rating' => $rating,
            'rating_count' => $ratingCount,
            'breakdown' => $this->normalizeBreakdown($latest?->rating_breakdown),
            'trend' => [
                'rating_delta_30d' => $ratingDelta,
                'rating_count_delta_30d' => $ratingCountDelta,
            ],
        ];
    }

    protected function getDefaultMessage(): string
    {
        return 'Rating summary retrieved successfully';
    }

    /**
     * PHP coerces numeric string keys to integers in arrays, which makes
     * `json_encode` emit a JSON array instead of an object. Return a stdClass
     * so the payload is always `{"1": n, ..., "5": n}` for the client.
     */
    private function normalizeBreakdown(mixed $breakdown): ?\stdClass
    {
        if (! is_array($breakdown)) {
            return null;
        }

        $normalized = new \stdClass;
        foreach (['1', '2', '3', '4', '5'] as $star) {
            $normalized->{$star} = (int) ($breakdown[$star] ?? 0);
        }

        return $normalized;
    }
}
