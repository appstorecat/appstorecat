<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\Country;

use App\Http\Resources\Api\BaseResource;
use App\Models\Country;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/** @mixin Country */
#[OA\Schema(
    schema: 'CountryResource',
    allOf: [new OA\Schema(ref: '#/components/schemas/Country')],
    properties: [
        new OA\Property(property: 'ios_languages', type: 'array', items: new OA\Items(type: 'string'), nullable: true),
        new OA\Property(property: 'android_languages', type: 'array', items: new OA\Items(type: 'string'), nullable: true),
    ],
)]
class CountryResource extends BaseResource
{
    protected function getResourceData(Request $request): array
    {
        return [
            'code' => $this->resource->code,
            'name' => $this->resource->name,
            'emoji' => $this->resource->emoji,
            'ios_languages' => $this->resource->ios_languages,
            'android_languages' => $this->resource->android_languages,
        ];
    }

    protected function getDefaultMessage(): string
    {
        return 'Country retrieved successfully';
    }
}
