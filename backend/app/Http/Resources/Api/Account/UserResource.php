<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\Account;

use App\Http\Resources\Api\BaseResource;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'UserResource',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'John Doe'),
        new OA\Property(property: 'email', type: 'string', format: 'email', example: 'user@example.com'),
        new OA\Property(property: 'email_verified_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
    ],
)]
class UserResource extends BaseResource
{
    protected function getResourceData(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'email' => $this->resource->email,
            'email_verified_at' => $this->formatTimestamp($this->resource->email_verified_at),
            'created_at' => $this->formatTimestamp($this->resource->created_at),
        ];
    }

    protected function getDefaultMessage(): string
    {
        return 'User retrieved successfully';
    }
}
