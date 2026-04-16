<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Account;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'RegisterRequest',
    required: ['name', 'email', 'password', 'password_confirmation'],
    properties: [
        new OA\Property(property: 'name', type: 'string', example: 'John Doe'),
        new OA\Property(property: 'email', type: 'string', format: 'email', example: 'user@example.com'),
        new OA\Property(property: 'password', type: 'string', format: 'password', example: 'password'),
        new OA\Property(property: 'password_confirmation', type: 'string', format: 'password', example: 'password'),
    ],
)]
class RegisterRequest extends FormRequest
{
    /**
     * @return array<string, array<int, \Illuminate\Contracts\Validation\Rule|string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)],
            'password' => ['required', 'string', Password::default(), 'confirmed'],
        ];
    }
}
