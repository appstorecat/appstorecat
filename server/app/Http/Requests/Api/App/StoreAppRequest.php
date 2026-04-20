<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\App;

use App\Enums\Platform;
use App\Models\App;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'StoreAppRequest',
    required: ['external_id', 'platform'],
    properties: [
        new OA\Property(property: 'external_id', type: 'string', example: '389801252'),
        new OA\Property(property: 'platform', type: 'string', enum: ['ios', 'android'], example: 'ios'),
    ],
)]
class StoreAppRequest extends FormRequest
{
    /**
     * @return array<string, array<int, Rule|string>>
     */
    public function rules(): array
    {
        return [
            'external_id' => ['required', 'string', 'max:255'],
            'platform' => ['required', 'in:ios,android'],
        ];
    }

    /**
     * Ensure the app has been discovered (search, chart, publisher, etc.)
     * before it can be registered. Direct creation of unknown apps is
     * blocked to keep the database free of unverified rows.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $platform = $this->input('platform');
            $externalId = $this->input('external_id');

            if (! $platform || ! $externalId) {
                return; // primary rules already surfaced the error
            }

            $exists = App::platform(Platform::fromSlug($platform))
                ->where('external_id', $externalId)
                ->exists();

            if (! $exists) {
                $validator->errors()->add(
                    'external_id',
                    'App not found. Search the store first to discover it.',
                );
            }
        });
    }
}
