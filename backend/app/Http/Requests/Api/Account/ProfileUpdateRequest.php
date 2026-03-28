<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Account;

use App\Concerns\ProfileValidationRules;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'ProfileUpdateRequest',
    properties: [
        new OA\Property(property: 'name', type: 'string', example: 'John Doe'),
        new OA\Property(property: 'email', type: 'string', format: 'email', example: 'user@example.com'),
    ],
)]
class ProfileUpdateRequest extends FormRequest
{
    use ProfileValidationRules;

    /**
     * @return array<string, array<int, Rule|string>>
     */
    public function rules(): array
    {
        return $this->profileRules($this->user()->id);
    }
}
