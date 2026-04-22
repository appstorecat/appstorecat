<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Change;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'ChangeAppsRequest',
    properties: [
        new OA\Property(property: 'per_page', type: 'integer', minimum: 1, maximum: 100, nullable: true),
        new OA\Property(property: 'field', type: 'string', enum: ['title', 'subtitle', 'description', 'whats_new', 'screenshots', 'locale_added', 'locale_removed'], nullable: true),
        new OA\Property(property: 'platform', type: 'string', enum: ['ios', 'android'], nullable: true),
        new OA\Property(property: 'search', type: 'string', maxLength: 100, nullable: true),
        new OA\Property(property: 'app_id', type: 'integer', minimum: 1, nullable: true),
        new OA\Property(property: 'page', type: 'integer', minimum: 1, nullable: true),
    ],
)]
class ChangeAppsRequest extends FormRequest
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
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'field' => ['sometimes', 'string', 'in:title,subtitle,description,whats_new,screenshots,locale_added,locale_removed'],
            'platform' => ['sometimes', 'string', 'in:ios,android'],
            'search' => ['sometimes', 'string', 'max:100'],
            'app_id' => ['sometimes', 'integer', 'min:1'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
