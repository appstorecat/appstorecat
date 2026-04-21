<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Change;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'ChangeCompetitorsRequest',
    properties: [
        new OA\Property(property: 'per_page', type: 'integer', minimum: 1, maximum: 100, nullable: true),
        new OA\Property(property: 'field', type: 'string', enum: ['title', 'subtitle', 'description', 'whats_new', 'screenshots', 'locale_removed'], nullable: true),
    ],
)]
class ChangeCompetitorsRequest extends FormRequest
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
            'field' => ['sometimes', 'string', 'in:title,subtitle,description,whats_new,screenshots,locale_removed'],
        ];
    }
}
