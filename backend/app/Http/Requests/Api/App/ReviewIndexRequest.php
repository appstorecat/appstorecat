<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\App;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'ReviewIndexRequest',
    properties: [
        new OA\Property(property: 'country_code', type: 'string', example: 'US'),
        new OA\Property(property: 'rating', type: 'integer', enum: [1, 2, 3, 4, 5], example: 5),
        new OA\Property(property: 'sort', type: 'string', enum: ['latest', 'oldest', 'highest', 'lowest'], example: 'latest'),
        new OA\Property(property: 'per_page', type: 'integer', example: 25),
    ],
)]
class ReviewIndexRequest extends FormRequest
{
    /**
     * @return array<string, array<int, Rule|string>>
     */
    public function rules(): array
    {
        return [
            'country_code' => ['sometimes', 'nullable', 'string', 'max:5'],
            'rating' => ['sometimes', 'nullable', 'integer', 'in:1,2,3,4,5'],
            'sort' => ['sometimes', 'nullable', 'string', 'in:latest,oldest,highest,lowest'],
            'per_page' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
