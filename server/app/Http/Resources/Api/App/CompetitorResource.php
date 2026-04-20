<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\App;

use App\Http\Resources\Api\BaseResource;
use App\Models\AppCompetitor;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/** @mixin AppCompetitor */
#[OA\Schema(
    schema: 'CompetitorResource',
    allOf: [new OA\Schema(ref: '#/components/schemas/AppCompetitor')],
    required: ['app'],
    properties: [
        new OA\Property(property: 'app', ref: '#/components/schemas/AppResource'),
    ],
)]
class CompetitorResource extends BaseResource
{
    protected function getResourceData(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'relationship' => $this->resource->relationship,
            'app' => new AppResource($this->resource->competitorApp),
            'created_at' => $this->formatTimestamp($this->resource->created_at),
        ];
    }

    protected function getDefaultMessage(): string
    {
        return 'Competitor retrieved successfully';
    }
}
