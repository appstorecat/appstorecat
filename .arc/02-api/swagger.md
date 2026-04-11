# Swagger / OpenAPI Conventions

## Overview
This project uses **l5-swagger v11** with **PHP 8 attributes** (NOT docblock annotations) for OpenAPI 3.0 documentation.

## Global Configuration
`BaseController` holds the global OpenAPI config:

```php
#[OA\Info(title: 'AppStoreCat API', version: '1.0.0')]
#[OA\Server(url: '/api')]
#[OA\SecurityScheme(
    securitySchemeId: 'bearerAuth',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'JWT',
)]
#[OA\Tag(name: 'Auth', description: 'Authentication')]
#[OA\Tag(name: 'Account', description: 'Account management')]
#[OA\Tag(name: 'Apps', description: 'App management')]
class BaseController extends Controller {}
```

## Schema Chain Pattern

Documentation follows a layered schema chain. Each layer references the next:

```
Controller (#[OA\Get/Post/...] with response $ref)
  → Resource (#[OA\Schema] with allOf → Model schema)
    → Model (#[OA\Schema] with #[OA\Property] definitions)
      → Enum (#[OA\Schema] with type + enum values)
```

### 1. Controller — Endpoint Documentation
```php
#[OA\Get(
    path: '/api/v1/apps/{app}',
    summary: 'Get app details',
    security: [['bearerAuth' => []]],
    tags: ['Apps'],
    parameters: [
        new OA\Parameter(name: 'app', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Success',
            content: new OA\JsonContent(ref: '#/components/schemas/AppDetailResource')),
        new OA\Response(response: 401, description: 'Unauthenticated'),
    ]
)]
```

### 2. Resource — Response Schema
```php
#[OA\Schema(
    schema: 'AppDetailResource',
    allOf: [new OA\Schema(ref: '#/components/schemas/App')],
)]
class AppDetailResource extends BaseResource { }
```

Use `allOf` to reference the Model schema. Only add extra `#[OA\Property]` for computed fields not on the model.

### 3. Model — Property Definitions
```php
#[OA\Schema(
    schema: 'App',
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'name', type: 'string'),
        new OA\Property(property: 'platform', ref: '#/components/schemas/Platform'),
        new OA\Property(property: 'build_status', ref: '#/components/schemas/BuildStatus'),
    ],
)]
class App extends Model { }
```

### 4. Enum — Value Definitions
```php
#[OA\Schema(schema: 'Platform', type: 'string', enum: ['android', 'ios'])]
enum Platform: string
{
    case Android = 'android';
    case Ios = 'ios';
}
```

## Key Rules

- **PHP 8 attributes only** — never use `@OA\` docblock annotations
- **Never define properties inline** in Resources when a Model schema exists — use `allOf`
- **Every endpoint** must have `#[OA\Get/Post/Put/Delete]` with response refs
- **Security** — add `security: [['bearerAuth' => []]]` to protected endpoints
- **Request bodies** — reference Form Request schemas: `new OA\JsonContent(ref: '#/components/schemas/StoreAppRequest')`

## Generation

```bash
# Generate OpenAPI JSON
./vendor/bin/sail artisan l5-swagger:generate

# Output: storage/api-docs/api-docs.json
```

## Orval Integration
Orval reads `storage/api-docs/api-docs.json` to generate typed React hooks and API functions.

```bash
cd frontend && npm run api:generate
```

## Workflow
After adding or changing any endpoint:
1. `./vendor/bin/sail artisan l5-swagger:generate`
2. `cd frontend && npm run api:generate`

This keeps the frontend API client in sync with the backend.
