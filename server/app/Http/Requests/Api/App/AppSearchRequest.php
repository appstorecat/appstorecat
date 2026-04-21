<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\App;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'AppSearchRequest',
    required: ['term', 'platform'],
    properties: [
        new OA\Property(property: 'term', type: 'string', minLength: 2, maxLength: 100, example: 'instagram'),
        new OA\Property(property: 'platform', type: 'string', enum: ['ios', 'android'], example: 'ios'),
        new OA\Property(property: 'country_code', type: 'string', minLength: 2, maxLength: 2, example: 'us'),
        new OA\Property(
            property: 'exclude_external_ids',
            type: 'array',
            items: new OA\Items(type: 'string', maxLength: 255),
            nullable: true,
        ),
    ],
)]
class AppSearchRequest extends FormRequest
{
    /**
     * @return array<string, array<int, Rule|string>>
     */
    public function rules(): array
    {
        return [
            'term' => ['required', 'string', 'min:2', 'max:100'],
            'platform' => ['required', 'in:ios,android'],
            'country_code' => ['sometimes', 'string', 'size:2', 'exists:countries,code'],
            'exclude_external_ids' => ['sometimes', 'nullable', 'array', 'max:50'],
            'exclude_external_ids.*' => ['string', 'max:255'],
        ];
    }
}
