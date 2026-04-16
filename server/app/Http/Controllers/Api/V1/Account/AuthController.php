<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Account;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\Account\LoginRequest;
use App\Http\Requests\Api\Account\RegisterRequest;
use App\Http\Resources\Api\Account\LoginResource;
use App\Http\Resources\Api\Account\UserResource;
use App\Http\Resources\Api\MessageResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use OpenApi\Attributes as OA;

class AuthController extends BaseController
{
    #[OA\Post(
        path: '/auth/register',
        summary: 'Register a new user',
        tags: ['Auth'],
        operationId: 'register',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/RegisterRequest'),
        ),
        responses: [
            new OA\Response(response: 201, description: 'Registration successful', content: new OA\JsonContent(ref: '#/components/schemas/LoginResource')),
            new OA\Response(response: 422, description: 'Validation error'),
        ],
    )]
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => $request->password,
        ]);

        $token = $user->createToken('auth-token')->plainTextToken;

        return LoginResource::make(['user' => $user, 'token' => $token])
            ->response()
            ->setStatusCode(201);
    }

    #[OA\Post(
        path: '/auth/login',
        summary: 'Login a user',
        tags: ['Auth'],
        operationId: 'login',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/LoginRequest'),
        ),
        responses: [
            new OA\Response(response: 200, description: 'Login successful', content: new OA\JsonContent(ref: '#/components/schemas/LoginResource')),
            new OA\Response(response: 401, description: 'Invalid credentials'),
        ],
    )]
    public function login(LoginRequest $request): LoginResource|JsonResponse
    {
        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return MessageResource::make('Invalid credentials')
                ->response()
                ->setStatusCode(401);
        }

        $user->tokens()->where('name', 'auth-token')->delete();

        $token = $user->createToken('auth-token')->plainTextToken;

        return new LoginResource(['user' => $user, 'token' => $token]);
    }

    #[OA\Post(
        path: '/auth/logout',
        summary: 'Logout the authenticated user',
        tags: ['Auth'],
        operationId: 'logout',
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 204, description: 'Logged out successfully'),
        ],
    )]
    public function logout(Request $request): Response
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }

    #[OA\Get(
        path: '/auth/me',
        summary: 'Get authenticated user info',
        tags: ['Auth'],
        operationId: 'me',
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'User info', content: new OA\JsonContent(ref: '#/components/schemas/UserResource')),
        ],
    )]
    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }
}
