<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\App;

use App\Http\Resources\Api\BaseResource;
use App\Models\AppKeywordDensity;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/** @mixin AppKeywordDensity */
#[OA\Schema(
    schema: 'KeywordDensityResource',
    required: ['id', 'keyword', 'count', 'density'],
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'language', type: 'string', example: 'us'),
        new OA\Property(property: 'ngram_size', type: 'integer', example: 2),
        new OA\Property(property: 'keyword', type: 'string', example: 'photo editor'),
        new OA\Property(property: 'count', type: 'integer', example: 5),
        new OA\Property(property: 'density', type: 'number', format: 'float', example: 2.35),
    ],
)]
class KeywordDensityResource extends BaseResource
{
    protected function getResourceData(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'language' => $this->resource->language,
            'ngram_size' => $this->resource->ngram_size,
            'keyword' => $this->resource->keyword,
            'count' => $this->resource->count,
            'density' => (float) $this->resource->density,
        ];
    }

    protected function getDefaultMessage(): string
    {
        return 'Keyword density retrieved successfully';
    }
}
