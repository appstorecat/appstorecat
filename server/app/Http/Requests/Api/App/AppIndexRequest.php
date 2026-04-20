<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\App;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'AppIndexRequest',
    properties: [
        new OA\Property(property: 'platform', type: 'string', enum: ['ios', 'android'], nullable: true),
        new OA\Property(property: 'search', type: 'string', maxLength: 100, nullable: true),
    ],
)]
class AppIndexRequest extends FormRequest
{
    /**
     * @return array<string, array<int, Rule|string>>
     */
    public function rules(): array
    {
        return [
            'platform' => ['sometimes', 'in:ios,android'],
            'search' => ['sometimes', 'string', 'max:100'],
        ];
    }
}
