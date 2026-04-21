<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Chart;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'ChartIndexRequest',
    required: ['platform', 'collection'],
    properties: [
        new OA\Property(property: 'platform', type: 'string', enum: ['ios', 'android']),
        new OA\Property(property: 'collection', type: 'string', enum: ['top_free', 'top_paid', 'top_grossing']),
        new OA\Property(property: 'country_code', type: 'string', minLength: 2, maxLength: 2, nullable: true),
        new OA\Property(property: 'category_id', type: 'integer', nullable: true),
    ],
)]
class ChartIndexRequest extends FormRequest
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
            'platform' => ['required', 'in:ios,android'],
            'collection' => ['required', 'in:top_free,top_paid,top_grossing'],
            'country_code' => ['sometimes', 'string', 'size:2', 'exists:countries,code'],
            'category_id' => ['sometimes', 'integer', 'exists:store_categories,id'],
        ];
    }
}
