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
        $row = $this->resource;
        $externalId = $row['external_id'] ?? null;

        // The current user's tracked external ids are injected onto the
        // request attribute bag by the controller (see
        // PublisherController::storeApps). The resource reads them back so
        // each row can compute `is_tracked` without the controller
        // reshaping the connector payload.
        $trackedIds = (array) $request->attributes->get('store_app_tracked_external_ids', []);
        $isTracked = $externalId !== null && in_array($externalId, $trackedIds, true)
            ? true
            : (bool) ($row['is_tracked'] ?? false);

        return [
            'external_id' => $externalId,
            'name' => $row['name'] ?? null,
            'icon_url' => $row['icon_url'] ?? null,
            'rating' => $row['rating'] ?? null,
            'rating_count' => $row['rating_count'] ?? null,
            'is_free' => $row['is_free'] ?? true,
            'category' => $row['category'] ?? null,
            'is_tracked' => $isTracked,
        ];
    }

    protected function getDefaultMessage(): string
    {
        return 'Store app retrieved successfully';
    }
}
