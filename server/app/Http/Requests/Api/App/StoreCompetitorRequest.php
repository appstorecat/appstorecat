<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\App;

use App\Enums\CompetitorRelationship;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'StoreCompetitorRequest',
    required: ['competitor_app_id'],
    properties: [
        new OA\Property(property: 'competitor_app_id', type: 'integer', example: 2),
        new OA\Property(property: 'relationship', type: 'string', enum: ['direct', 'indirect', 'aspiration'], example: 'direct'),
    ],
)]
class StoreCompetitorRequest extends FormRequest
{
    /**
     * @return array<string, array<int, \Illuminate\Contracts\Validation\Rule|string>>
     */
    public function rules(): array
    {
        return [
            'competitor_app_id' => ['required', 'integer', 'exists:apps,id'],
            'relationship' => ['sometimes', Rule::enum(CompetitorRelationship::class)],
        ];
    }
}
