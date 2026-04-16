<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Account;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\Account\StoreApiTokenRequest;
use App\Http\Resources\Api\Account\ApiTokenResource;
use App\Http\Resources\Api\MessageResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use OpenApi\Attributes as OA;

class ApiTokenController extends BaseController
{
    #[OA\Get(
        path: '/account/api-tokens',
        summary: 'List all API tokens',
        tags: ['Account'],
        operationId: 'listApiTokens',
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Token list',
                content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/ApiTokenResource')),
            ),
        ],
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $tokens = $request->user()
            ->tokens()
            ->where('name', '!=', 'auth-token')
            ->orderByDesc('created_at')
            ->get();

        return ApiTokenResource::collection($tokens);
    }

    #[OA\Post(
        path: '/account/api-tokens',
        summary: 'Create a new API token',
        tags: ['Account'],
        operationId: 'createApiToken',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/StoreApiTokenRequest'),
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Token created',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'token', ref: '#/components/schemas/ApiTokenResource'),
                        new OA\Property(property: 'plain_text_token', type: 'string', example: '1|abc123...'),
                    ],
                ),
            ),
            new OA\Response(response: 422, description: 'Validation error'),
        ],
    )]
    public function store(StoreApiTokenRequest $request): JsonResponse
    {
        $newToken = $request->user()->createToken($request->name, ['mcp']);

        return response()->json([
            'token' => (new ApiTokenResource($newToken->accessToken))->toArray($request),
            'plain_text_token' => $newToken->plainTextToken,
        ], 201);
    }

    #[OA\Delete(
        path: '/account/api-tokens/{tokenId}',
        summary: 'Revoke an API token',
        tags: ['Account'],
        operationId: 'revokeApiToken',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'tokenId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Token revoked'),
            new OA\Response(response: 404, description: 'Token not found'),
        ],
    )]
    public function destroy(Request $request, int $tokenId): Response
    {
        $request->user()
            ->tokens()
            ->where('id', $tokenId)
            ->where('name', '!=', 'auth-token')
            ->firstOrFail()
            ->delete();

        return response()->noContent();
    }
}
