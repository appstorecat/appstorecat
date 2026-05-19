<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\App;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule as ValidationRule;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'AppIndexRequest',
    properties: [
        new OA\Property(property: 'platform', type: 'string', enum: ['ios', 'android'], nullable: true),
        new OA\Property(property: 'search', type: 'string', maxLength: 100, nullable: true),
        new OA\Property(
            property: 'folder_id',
            type: 'string',
            nullable: true,
            description: 'Filter by folder. Pass an integer for a specific folder, `null` or `unassigned` for tracked apps without a folder, or omit for all tracked apps.',
        ),
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
            'folder_id' => ['sometimes', 'nullable'],
        ];
    }

    /**
     * Resolve the `folder_id` filter into one of three modes:
     * - `null` → no folder filter (return all tracked apps)
     * - `'unassigned'` → only apps with `user_apps.folder_id IS NULL`
     * - integer → restrict to that folder (must belong to the user)
     */
    public function folderFilter(): null|int|string
    {
        if (! $this->has('folder_id')) {
            return null;
        }

        $value = $this->input('folder_id');

        if ($value === null || $value === '' || $value === 'null' || $value === 'unassigned') {
            return 'unassigned';
        }

        return (int) $value;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $folderId = $this->folderFilter();

            if (! is_int($folderId)) {
                return;
            }

            $exists = ValidationRule::exists('folders', 'id')
                ->where(fn ($query) => $query->where('user_id', $this->user()->id));

            $passes = validator(
                ['folder_id' => $folderId],
                ['folder_id' => [$exists]],
            )->passes();

            if (! $passes) {
                $validator->errors()->add('folder_id', 'Folder not found.');
            }
        });
    }
}
