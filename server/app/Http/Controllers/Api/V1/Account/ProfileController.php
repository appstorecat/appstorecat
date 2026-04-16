<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Account;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\Account\ProfileDeleteRequest;
use App\Http\Requests\Api\Account\ProfileUpdateRequest;
use App\Http\Resources\Api\Account\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use OpenApi\Attributes as OA;

class ProfileController extends BaseController
{
    #[OA\Get(
        path: '/account/profile',
        summary: 'Get authenticated user profile',
        tags: ['Account'],
        operationId: 'showProfile',
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'User profile', content: new OA\JsonContent(ref: '#/components/schemas/UserResource')),
        ],
    )]
    public function show(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    #[OA\Patch(
        path: '/account/profile',
        summary: 'Update user profile',
        tags: ['Account'],
        operationId: 'updateProfile',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/ProfileUpdateRequest'),
        ),
        responses: [
            new OA\Response(response: 200, description: 'Profile updated', content: new OA\JsonContent(ref: '#/components/schemas/UserResource')),
            new OA\Response(response: 422, description: 'Validation error'),
        ],
    )]
    public function update(ProfileUpdateRequest $request): UserResource
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return new UserResource($request->user());
    }

    #[OA\Delete(
        path: '/account/profile',
        summary: 'Delete user account',
        tags: ['Account'],
        operationId: 'deleteProfile',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/ProfileDeleteRequest'),
        ),
        responses: [
            new OA\Response(response: 204, description: 'Account deleted'),
        ],
    )]
    public function destroy(ProfileDeleteRequest $request): Response
    {
        $user = $request->user();

        $user->tokens()->delete();
        $user->delete();

        return response()->noContent();
    }
}
