<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\Account;

use App\Http\Resources\Api\BaseResource;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'LoginResource',
    properties: [
        new OA\Property(property: 'user', ref: '#/components/schemas/UserResource'),
        new OA\Property(property: 'token', type: 'string', example: '1|abc123...'),
    ],
)]
class LoginResource extends BaseResource
{
    protected function getResourceData(Request $request): array
    {
        return [
            'user' => new UserResource($this->resource['user']),
            'token' => $this->resource['token'],
        ];
    }

    protected function getDefaultMessage(): string
    {
        return 'Login successful';
    }
}
