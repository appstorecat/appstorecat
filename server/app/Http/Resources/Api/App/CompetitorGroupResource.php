<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\App;

use App\Http\Resources\Api\BaseResource;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'CompetitorGroupResource',
    required: ['parent', 'competitors'],
    properties: [
        new OA\Property(property: 'parent', ref: '#/components/schemas/AppResource'),
        new OA\Property(
            property: 'competitors',
            type: 'array',
            items: new OA\Items(ref: '#/components/schemas/CompetitorResource'),
        ),
    ],
)]
class CompetitorGroupResource extends BaseResource
{
    protected function getResourceData(Request $request): array
    {
        return [
            'parent' => new AppResource($this->resource['parent']),
            'competitors' => CompetitorResource::collection($this->resource['competitors']),
        ];
    }

    protected function getDefaultMessage(): string
    {
        return 'Competitor group retrieved successfully';
    }
}
