<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Account;

use App\Concerns\PasswordValidationRules;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'ProfileDeleteRequest',
    required: ['password'],
    properties: [
        new OA\Property(property: 'password', type: 'string', format: 'password'),
    ],
)]
class ProfileDeleteRequest extends FormRequest
{
    use PasswordValidationRules;

    /**
     * @return array<string, array<int, Rule|string>>
     */
    public function rules(): array
    {
        return [
            'password' => $this->currentPasswordRules(),
        ];
    }
}
