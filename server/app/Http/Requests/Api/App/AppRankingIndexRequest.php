<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\App;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'AppRankingIndexRequest',
    properties: [
        new OA\Property(property: 'date', type: 'string', format: 'date', example: '2026-04-17'),
    ],
)]
class AppRankingIndexRequest extends FormRequest
{
    /**
     * @return array<string, array<int, Rule|string>>
     */
    public function rules(): array
    {
        return [
            'date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
        ];
    }
}
