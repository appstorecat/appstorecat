<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Explorer;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'ExplorerIconsRequest',
    properties: [
        new OA\Property(property: 'platform', type: 'string', enum: ['ios', 'android'], nullable: true),
        new OA\Property(property: 'category_id', type: 'integer', nullable: true),
        new OA\Property(property: 'search', type: 'string', maxLength: 100, nullable: true),
        new OA\Property(property: 'per_page', type: 'integer', minimum: 1, maximum: 200, nullable: true),
    ],
)]
class ExplorerIconsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, Rule|string>>
     */
    public function rules(): array
    {
        return [
            'platform' => ['sometimes', 'in:ios,android'],
            'category_id' => ['sometimes', 'integer', 'exists:store_categories,id'],
            'search' => ['sometimes', 'string', 'max:100'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:200'],
        ];
    }
}
