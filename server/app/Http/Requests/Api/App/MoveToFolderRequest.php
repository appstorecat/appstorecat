<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\App;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule as ValidationRule;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'MoveToFolderRequest',
    required: ['folder_id'],
    properties: [
        new OA\Property(
            property: 'folder_id',
            type: 'integer',
            nullable: true,
            example: 1,
            description: 'Target folder id, or null to remove the app from its current folder.',
        ),
    ],
)]
class MoveToFolderRequest extends FormRequest
{
    /**
     * @return array<string, array<int, Rule|string>>
     */
    public function rules(): array
    {
        return [
            'folder_id' => [
                'present',
                'nullable',
                'integer',
                ValidationRule::exists('folders', 'id')->where(
                    fn ($query) => $query->where('user_id', $this->user()->id),
                ),
            ],
        ];
    }
}
