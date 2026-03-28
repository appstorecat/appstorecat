<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Account;

use App\Concerns\PasswordValidationRules;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'PasswordUpdateRequest',
    required: ['current_password', 'password', 'password_confirmation'],
    properties: [
        new OA\Property(property: 'current_password', type: 'string', format: 'password'),
        new OA\Property(property: 'password', type: 'string', format: 'password'),
        new OA\Property(property: 'password_confirmation', type: 'string', format: 'password'),
    ],
)]
class PasswordUpdateRequest extends FormRequest
{
    use PasswordValidationRules;

    /**
     * @return array<string, array<int, Rule|string>>
     */
    public function rules(): array
    {
        return [
            'current_password' => $this->currentPasswordRules(),
            'password' => $this->passwordRules(),
        ];
    }
}
