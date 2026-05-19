<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule as ValidationRule;

/**
 * Shared `folder_id` query filter handling for tracked-app endpoints.
 *
 * Resolves the incoming value into one of three modes (see {@see folderFilter()})
 * and adds a "must belong to the authenticated user" existence check for any
 * integer value. Use on a `FormRequest` that already authenticates the user.
 */
trait ResolvesFolderFilter
{
    /**
     * Resolve the `folder_id` filter into one of three modes:
     * - `null` → no folder filter (caller should not constrain by folder)
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

    /**
     * Register an after-validation check that the integer `folder_id` belongs
     * to the authenticated user. No-op for `null`/`'unassigned'`.
     */
    protected function validateFolderBelongsToUser(Validator $validator): void
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
