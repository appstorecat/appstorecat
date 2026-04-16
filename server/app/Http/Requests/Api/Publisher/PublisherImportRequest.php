<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Publisher;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'PublisherImportRequest',
    required: ['external_ids'],
    properties: [
        new OA\Property(property: 'external_ids', type: 'array', items: new OA\Items(type: 'string')),
    ],
)]
class PublisherImportRequest extends FormRequest
{
    /**
     * @return array<string, array<int, Rule|string>>
     */
    public function rules(): array
    {
        return [
            'external_ids' => ['required', 'array', 'min:1', 'max:50'],
            'external_ids.*' => ['required', 'string'],
        ];
    }
}
