<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\App;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'RatingHistoryRequest',
    properties: [
        new OA\Property(property: 'months', type: 'integer', minimum: 1, maximum: 24, example: 12),
    ],
)]
class RatingHistoryRequest extends FormRequest
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
            'months' => ['sometimes', 'integer', 'min:1', 'max:24'],
        ];
    }

    public function months(): int
    {
        return (int) ($this->validated('months') ?? 12);
    }
}
