<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\Account;

use App\Http\Resources\Api\BaseResource;
use Illuminate\Http\Request;
use Laravel\Sanctum\NewAccessToken;
use OpenApi\Attributes as OA;

/**
 * Response shape returned immediately after an API token is created.
 *
 * The plaintext token is a one-time secret — it is only available on this
 * create response and must be rendered through a dedicated schema so the
 * OpenAPI spec makes the `writeOnce` nature explicit.
 */
#[OA\Schema(
    schema: 'ApiTokenCreatedResource',
    required: ['token', 'plain_text_token'],
    properties: [
        new OA\Property(property: 'token', ref: '#/components/schemas/ApiTokenResource'),
        new OA\Property(property: 'plain_text_token', type: 'string', example: '1|abc123...'),
    ],
)]
class ApiTokenCreatedResource extends BaseResource
{
    protected function getResourceData(Request $request): array
    {
        /** @var NewAccessToken $newToken */
        $newToken = $this->resource;

        return [
            'token' => (new ApiTokenResource($newToken->accessToken))->toArray($request),
            'plain_text_token' => $newToken->plainTextToken,
        ];
    }

    protected function getDefaultMessage(): string
    {
        return 'API token created successfully';
    }
}
