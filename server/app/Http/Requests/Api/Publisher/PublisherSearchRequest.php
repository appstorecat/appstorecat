<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Publisher;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'PublisherSearchRequest',
    required: ['term', 'platform'],
    properties: [
        new OA\Property(property: 'term', type: 'string', minLength: 2, maxLength: 100, example: 'Instagram'),
        new OA\Property(property: 'platform', type: 'string', enum: ['ios', 'android'], example: 'ios'),
        new OA\Property(property: 'country_code', type: 'string', minLength: 2, maxLength: 2, example: 'us'),
    ],
)]
class PublisherSearchRequest extends FormRequest
{
    /**
     * @return array<string, array<int, Rule|string>>
     */
    public function rules(): array
    {
        return [
            'term' => ['required', 'string', 'min:2', 'max:100'],
            'platform' => ['required', 'string', 'in:ios,android'],
            'country_code' => ['sometimes', 'string', 'size:2', 'exists:countries,code'],
        ];
    }
}
