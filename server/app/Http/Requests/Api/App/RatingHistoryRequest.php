<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\App;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'RatingHistoryRequest',
    properties: [
        new OA\Property(property: 'days', type: 'integer', minimum: 1, maximum: 90, example: 30),
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
            'days' => ['sometimes', 'integer', 'min:1', 'max:90'],
        ];
    }

    public function days(): int
    {
        return (int) ($this->validated('days') ?? 30);
    }
}
