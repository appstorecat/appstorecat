<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Account;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\Account\PasswordUpdateRequest;
use App\Http\Resources\Api\MessageResource;
use OpenApi\Attributes as OA;

class SecurityController extends BaseController
{
    #[OA\Put(
        path: '/account/password',
        summary: 'Update user password',
        tags: ['Account'],
        operationId: 'updatePassword',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/PasswordUpdateRequest'),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Password updated',
                content: new OA\JsonContent(ref: '#/components/schemas/MessageResource'),
            ),
            new OA\Response(response: 422, description: 'Validation error'),
        ],
    )]
    public function updatePassword(PasswordUpdateRequest $request): MessageResource
    {
        $request->user()->update([
            'password' => $request->password,
        ]);

        return new MessageResource('Password updated');
    }
}
