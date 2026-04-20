<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\App;
use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'AppStoreCat API',
    description: 'Open source app intelligence toolkit API. Provides endpoints for app registration, DNA building, store listing tracking, and analytics.',
)]
#[OA\Server(url: L5_SWAGGER_CONST_HOST, description: 'API Server')]
#[OA\SecurityScheme(
    securityScheme: 'bearerAuth',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'Sanctum',
    description: 'Authentication using Bearer token. Example: Bearer {your-token}',
)]
#[OA\Tag(name: 'Auth', description: 'Authentication endpoints')]
#[OA\Tag(name: 'Account', description: 'Account and profile management')]
#[OA\Tag(name: 'Apps', description: 'App registration and management')]
abstract class BaseController extends Controller
{
    protected function resolveApp(string $platform, string $externalId): App
    {
        return App::platform($platform)
            ->where('external_id', $externalId)
            ->firstOrFail();
    }

    protected function absoluteUrl(?string $url): ?string
    {
        if ($url === null) {
            return null;
        }

        if (str_starts_with($url, '/storage/')) {
            return rtrim(config('app.url'), '/').$url;
        }

        return $url;
    }
}
