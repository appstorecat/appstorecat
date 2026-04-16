<?php

declare(strict_types=1);

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'MessageResource',
    required: ['message'],
    properties: [
        new OA\Property(property: 'message', type: 'string', example: 'Operation completed successfully'),
    ],
)]
class MessageResource extends BaseResource
{
    protected function getResourceData(Request $request): array
    {
        return [
            'message' => $this->resource,
        ];
    }

    protected function getDefaultMessage(): string
    {
        return 'Operation completed successfully';
    }
}
