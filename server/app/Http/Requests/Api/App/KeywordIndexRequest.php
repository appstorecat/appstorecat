<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\App;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'KeywordIndexRequest',
    properties: [
        new OA\Property(property: 'language', type: 'string', example: 'us'),
        new OA\Property(property: 'ngram', type: 'integer', enum: [1, 2, 3, 4], example: 1),
        new OA\Property(property: 'version_id', type: 'integer', example: 1),
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
            'language' => ['sometimes', 'nullable', 'string', 'max:10'],
            'ngram' => ['sometimes', 'nullable', 'integer', 'in:1,2,3,4'],
            'version_id' => ['sometimes', 'nullable', 'integer', 'exists:app_versions,id'],
        ];
    }
}
