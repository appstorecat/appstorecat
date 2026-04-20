<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\Account;

use App\Http\Resources\Api\BaseResource;
use App\Models\User;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/** @mixin User */
#[OA\Schema(
    schema: 'UserResource',
    allOf: [new OA\Schema(ref: '#/components/schemas/User')],
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
