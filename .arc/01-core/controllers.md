# Controller Conventions

## API-Only Controllers

All controllers are API-only. Located at `app/Http/Controllers/Api/V1/{Domain}/`.

Every controller extends `App\Http\Controllers\Api\BaseController` and has OpenAPI PHP 8 attributes on every method.

```php
use App\Http\Controllers\Api\BaseController;
use App\Http\Resources\Api\App\AppDetailResource;
use OpenApi\Attributes as OA;

class AppController extends BaseController
{
    public function __construct(
        private readonly AppRegistrar $appRegistrar,
    ) {}

    #[OA\Get(
        path: '/api/v1/apps/{app}',
        summary: 'Get app details',
        tags: ['Apps'],
        parameters: [
            new OA\Parameter(name: 'app', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Success', content: new OA\JsonContent(ref: '#/components/schemas/AppDetailResource')),
        ]
    )]
    public function show(App $app): AppDetailResource
    {
        return new AppDetailResource($app->load('developer', 'category'));
    }
}
```

## Structure Rules

### Domain Grouping
Controllers are grouped by domain under `Api/V1/`:

```
Api/V1/Account/    # Auth, Profile, Security (LoginController, RegisterController, etc.)
Api/V1/App/        # Apps, Search, BuildStatus (AppController, SearchController, etc.)
```

### Constructor Injection
Services injected via `private readonly` promoted properties.

```php
public function __construct(
    private readonly AppRegistrar $appRegistrar,
    private readonly DnaBuilder $dnaBuilder,
) {}
```

### Response Format
- Return `Resource`, `JsonResponse`, or `Response` (never `Inertia::render`)
- Use `Symfony\Component\HttpFoundation\Response::HTTP_*` constants for status codes
- Return Resource instances directly (Laravel auto-converts to JSON)

```php
// Single resource
return new AppDetailResource($app);

// Collection
return AppResource::collection($apps);

// Empty response
return response()->json(null, Response::HTTP_NO_CONTENT);

// Custom JSON
return response()->json(['message' => 'Success'], Response::HTTP_OK);
```

### OpenAPI Attributes
Every public controller method MUST have `#[OA\Get]`, `#[OA\Post]`, `#[OA\Put]`, `#[OA\Delete]`, etc. Response annotations reference Resource schemas.

## DO / DON'T
- DO: Keep controllers thin, delegate to services
- DO: Use Form Request for validation (never validate in controller)
- DO: Use Resource classes for data transformation
- DO: Add OpenAPI attributes on every endpoint method
- DO: Group controllers by domain under `Api/V1/{Domain}/`
- DON'T: Put business logic in controllers
- DON'T: Use integer status codes (use `Response::HTTP_*`)
- DON'T: Return raw arrays from controllers
- DON'T: Define properties inline in OpenAPI when a schema exists
