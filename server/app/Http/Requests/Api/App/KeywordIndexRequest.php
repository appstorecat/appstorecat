<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\App;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'KeywordIndexRequest',
    properties: [
        new OA\Property(property: 'locale', type: 'string', example: 'en-US'),
        new OA\Property(property: 'ngram', type: 'integer', enum: [1, 2, 3, 4], example: 1),
        new OA\Property(property: 'version_id', type: 'integer', example: 1),
        new OA\Property(property: 'search', type: 'string', maxLength: 100, nullable: true),
        new OA\Property(property: 'sort', type: 'string', enum: ['keyword', 'count', 'density'], nullable: true),
        new OA\Property(property: 'order', type: 'string', enum: ['asc', 'desc'], nullable: true),
        new OA\Property(property: 'per_page', type: 'integer', minimum: 1, maximum: 500, nullable: true),
        new OA\Property(property: 'page', type: 'integer', minimum: 1, nullable: true),
    ],
)]
class KeywordIndexRequest extends FormRequest
{
    /**
     * @return array<string, array<int, Rule|string>>
     */
    public function rules(): array
    {
        return [
            'locale' => ['sometimes', 'nullable', 'string', 'max:10'],
            'ngram' => ['sometimes', 'nullable', 'integer', 'in:1,2,3,4'],
            'version_id' => ['sometimes', 'nullable', 'integer', 'exists:app_versions,id'],
            'search' => ['sometimes', 'nullable', 'string', 'max:100'],
            'sort' => ['sometimes', 'nullable', 'string', 'in:keyword,count,density'],
            'order' => ['sometimes', 'nullable', 'string', 'in:asc,desc'],
            'per_page' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:500'],
            'page' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ];
    }
}
