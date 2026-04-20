<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\App;

use App\Http\Resources\Api\BaseResource;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'KeywordDensityResource',
    required: ['keyword', 'count', 'density'],
    properties: [
        new OA\Property(property: 'locale', type: 'string', example: 'en-US'),
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
        /** @var array{keyword:string,count:int,density:float,ngram_size:int,locale?:string} $data */
        $data = $this->resource;

        return [
            'locale' => $data['locale'] ?? null,
            'ngram_size' => $data['ngram_size'],
            'keyword' => $data['keyword'],
            'count' => $data['count'],
            'density' => (float) $data['density'],
        ];
    }

    protected function getDefaultMessage(): string
    {
        return 'Keyword density retrieved successfully';
    }
}
