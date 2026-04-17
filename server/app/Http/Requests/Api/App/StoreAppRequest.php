<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\App;

use App\Enums\Platform;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
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
     * @return array<string, array<int, \Illuminate\Contracts\Validation\Rule|string>>
     */
    public function rules(): array
    {
        return [
            'external_id' => ['required', 'string', 'max:255'],
            'platform' => ['required', 'in:ios,android'],
        ];
    }
}
