<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\App;

use App\Http\Resources\Api\BaseResource;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'KeywordCompareResource',
    required: ['apps', 'keywords'],
    properties: [
        new OA\Property(
            property: 'apps',
            type: 'array',
            items: new OA\Items(properties: [
                new OA\Property(property: 'id', type: 'integer'),
                new OA\Property(property: 'name', type: 'string'),
                new OA\Property(property: 'icon_url', type: 'string', nullable: true),
            ], type: 'object'),
        ),
        new OA\Property(property: 'keywords', type: 'object', additionalProperties: new OA\AdditionalProperties(type: 'object')),
    ],
)]
class KeywordCompareResource extends BaseResource
{
    protected function getResourceData(Request $request): array
    {
        return [
            'apps' => $this->resource['apps'],
            'keywords' => $this->resource['keywords'],
        ];
    }

    protected function getDefaultMessage(): string
    {
        return 'Keyword comparison retrieved successfully';
    }
}
