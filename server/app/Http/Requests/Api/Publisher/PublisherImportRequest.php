<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Publisher;

use App\Enums\Platform;
use App\Models\App;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Contracts\Validation\Validator;
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

    /**
     * All imported IDs must already exist in the DB (discovered via search,
     * charts, or publisher page scraping). Blocks ad-hoc creation of
     * unverified rows.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $platformSlug = $this->route('platform');
            $externalIds = (array) $this->input('external_ids', []);

            if (! $platformSlug || $externalIds === []) {
                return;
            }

            $platform = Platform::fromSlug($platformSlug);
            $known = App::platform($platform)
                ->whereIn('external_id', $externalIds)
                ->pluck('external_id')
                ->all();

            $missing = array_values(array_diff($externalIds, $known));
            foreach ($missing as $id) {
                $validator->errors()->add(
                    'external_ids',
                    "App '{$id}' not found. Discover it via search or publisher apps first.",
                );
            }
        });
    }
}
