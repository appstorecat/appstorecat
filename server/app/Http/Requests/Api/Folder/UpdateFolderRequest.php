<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Folder;

use App\Enums\FolderColor;
use App\Models\Folder;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule as ValidationRule;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'UpdateFolderRequest',
    properties: [
        new OA\Property(property: 'name', type: 'string', maxLength: 60, nullable: true, example: 'Virtual Number'),
        new OA\Property(property: 'color', ref: '#/components/schemas/FolderColorEnum', nullable: true),
        new OA\Property(property: 'sort_order', type: 'integer', nullable: true, example: 1),
    ],
)]
class UpdateFolderRequest extends FormRequest
{
    /**
     * @return array<string, array<int, Rule|string>>
     */
    public function rules(): array
    {
        /** @var Folder $folder */
        $folder = $this->route('folder');

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:60',
                ValidationRule::unique('folders', 'name')
                    ->where(fn ($query) => $query->where('user_id', $this->user()->id))
                    ->ignore($folder->id),
            ],
            'color' => ['sometimes', 'required', ValidationRule::enum(FolderColor::class)],
            'sort_order' => ['sometimes', 'integer'],
        ];
    }
}
