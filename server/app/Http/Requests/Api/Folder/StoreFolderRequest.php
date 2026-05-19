<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Folder;

use App\Enums\FolderColor;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule as ValidationRule;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'StoreFolderRequest',
    required: ['name', 'color'],
    properties: [
        new OA\Property(property: 'name', type: 'string', maxLength: 60, example: 'Virtual Number'),
        new OA\Property(property: 'color', ref: '#/components/schemas/FolderColorEnum'),
        new OA\Property(property: 'sort_order', type: 'integer', nullable: true, example: 0),
    ],
)]
class StoreFolderRequest extends FormRequest
{
    /**
     * @return array<string, array<int, Rule|string>>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:60',
                ValidationRule::unique('folders', 'name')->where(
                    fn ($query) => $query->where('user_id', $this->user()->id),
                ),
            ],
            'color' => ['required', ValidationRule::enum(FolderColor::class)],
            'sort_order' => ['sometimes', 'integer'],
        ];
    }
}
