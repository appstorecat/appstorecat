<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\StoreCategory;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'StoreCategoryIndexRequest',
    properties: [
        new OA\Property(property: 'platform', type: 'string', enum: ['ios', 'android'], nullable: true),
        new OA\Property(property: 'type', type: 'string', enum: ['app', 'game', 'magazine'], nullable: true),
    ],
)]
class StoreCategoryIndexRequest extends FormRequest
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
            'type' => ['sometimes', 'in:app,game,magazine'],
        ];
    }
}
