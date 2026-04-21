<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\Explorer;

use App\Http\Resources\Api\BaseResource;
use App\Models\App;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/** @mixin App */
#[OA\Schema(
    schema: 'ExplorerScreenshotResource',
    properties: [
        new OA\Property(property: 'app_id', type: 'integer'),
        new OA\Property(property: 'external_id', type: 'string'),
        new OA\Property(property: 'platform', type: 'string', enum: ['ios', 'android']),
        new OA\Property(property: 'name', type: 'string'),
        new OA\Property(property: 'icon_url', type: 'string', nullable: true),
        new OA\Property(property: 'publisher_name', type: 'string', nullable: true),
        new OA\Property(property: 'category_name', type: 'string', nullable: true),
        new OA\Property(
            property: 'screenshots',
            type: 'array',
            items: new OA\Items(
                type: 'object',
                required: ['url', 'device_type', 'order'],
                properties: [
                    new OA\Property(property: 'url', type: 'string'),
                    new OA\Property(property: 'device_type', type: 'string', example: 'iphone'),
                    new OA\Property(property: 'order', type: 'integer'),
                ],
            ),
        ),
    ],
)]
class ExplorerScreenshotResource extends BaseResource
{
    protected function getResourceData(Request $request): array
    {
        /** @var App $app */
        $app = $this->resource;
        $listing = $app->storeListings->first();

        return [
            'app_id' => $app->id,
            'external_id' => $app->external_id,
            'platform' => $app->platform->slug(),
            'name' => $app->displayName(),
            'icon_url' => $listing?->icon_url ?? $app->displayIcon(),
            'publisher_name' => $app->publisher?->name,
            'category_name' => $app->category?->name,
            'screenshots' => $listing?->screenshotUrls() ?? [],
        ];
    }

    protected function getDefaultMessage(): string
    {
        return 'Explorer screenshot retrieved successfully';
    }
}
