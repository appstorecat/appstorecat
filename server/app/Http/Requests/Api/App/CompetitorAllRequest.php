<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\App;

use App\Http\Requests\Concerns\ResolvesFolderFilter;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'CompetitorAllRequest',
    properties: [
        new OA\Property(property: 'platform', type: 'string', enum: ['ios', 'android'], nullable: true),
        new OA\Property(property: 'search', type: 'string', maxLength: 100, nullable: true),
        new OA\Property(
            property: 'folder_id',
            type: 'string',
            nullable: true,
            description: 'Filter by the parent app\'s folder. Pass an integer for a specific folder, `null` or `unassigned` for tracked apps without a folder, or omit to include every parent.',
        ),
    ],
)]
class CompetitorAllRequest extends FormRequest
{
    use ResolvesFolderFilter;

    /**
     * @return array<string, array<int, Rule|string>>
     */
    public function rules(): array
    {
        return [
            'platform' => ['sometimes', 'nullable', 'string', 'in:ios,android'],
            'search' => ['sometimes', 'nullable', 'string', 'max:100'],
            'folder_id' => ['sometimes', 'nullable'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $this->validateFolderBelongsToUser($validator);
    }
}
