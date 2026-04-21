<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\App;

use App\Rules\AppAvailableCountry;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'ListingRequest',
    required: ['country_code', 'locale'],
    properties: [
        new OA\Property(property: 'country_code', type: 'string', minLength: 2, maxLength: 2, example: 'tr'),
        new OA\Property(property: 'locale', type: 'string', maxLength: 10, example: 'tr'),
    ],
)]
class ListingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, Rule|string|ValidationRule>>
     */
    public function rules(): array
    {
        $platform = (string) $this->route('platform');
        $externalId = (string) $this->route('externalId');

        return [
            'country_code' => [
                'required', 'string', 'size:2', 'exists:countries,code',
                new AppAvailableCountry($platform, $externalId),
            ],
            'locale' => ['required', 'string', 'max:10'],
        ];
    }
}
