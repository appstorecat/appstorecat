<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\Account;

use App\Http\Resources\Api\BaseResource;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'ApiTokenResource',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'My MCP Token'),
        new OA\Property(property: 'abilities', type: 'array', items: new OA\Items(type: 'string'), example: '["mcp"]'),
        new OA\Property(property: 'last_used_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
    ],
)]
class ApiTokenResource extends BaseResource
{
    protected function getResourceData(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'abilities' => $this->resource->abilities,
            'last_used_at' => $this->formatTimestamp($this->resource->last_used_at),
            'created_at' => $this->formatTimestamp($this->resource->created_at),
        ];
    }

    protected function getDefaultMessage(): string
    {
        return 'API token retrieved successfully';
    }
}
