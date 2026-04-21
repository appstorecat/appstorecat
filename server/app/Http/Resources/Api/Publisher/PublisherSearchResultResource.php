<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\Publisher;

use App\Http\Resources\Api\BaseResource;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'PublisherSearchResultResource',
    required: ['external_id', 'name', 'platform'],
    properties: [
        new OA\Property(property: 'external_id', type: 'string', example: '389801255'),
        new OA\Property(property: 'name', type: 'string', example: 'Meta Platforms, Inc.'),
        new OA\Property(property: 'url', type: 'string', nullable: true),
        new OA\Property(property: 'platform', type: 'string', enum: ['ios', 'android'], example: 'ios'),
        new OA\Property(property: 'app_count', type: 'integer', example: 5),
        new OA\Property(
            property: 'sample_apps',
            type: 'array',
            items: new OA\Items(
                properties: [
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'icon_url', type: 'string', nullable: true),
                ],
            ),
        ),
    ],
)]
class PublisherSearchResultResource extends BaseResource
{
    protected function getResourceData(Request $request): array
    {
        return [
            'external_id' => $this->resource['external_id'],
            'name' => $this->resource['name'],
            'url' => $this->resource['url'] ?? null,
            'platform' => $this->resource['platform'],
            'app_count' => $this->resource['app_count'],
            'sample_apps' => $this->resource['sample_apps'] ?? [],
        ];
    }

    protected function getDefaultMessage(): string
    {
        return 'Publisher search results retrieved successfully';
    }
}
