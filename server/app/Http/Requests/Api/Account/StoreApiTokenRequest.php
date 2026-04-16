<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Account;

use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'StoreApiTokenRequest',
    required: ['name'],
    properties: [
        new OA\Property(property: 'name', type: 'string', example: 'My MCP Token', maxLength: 255),
    ],
)]
class StoreApiTokenRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
        ];
    }
}
