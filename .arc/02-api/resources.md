# Resource Conventions

## API-Only Resources

Resources serve API responses only. All resources extend `BaseResource` which provides an abstract `getResourceData()` template method.

```php
use App\Http\Resources\Api\BaseResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'AppResource',
    allOf: [new OA\Schema(ref: '#/components/schemas/App')],
)]
class AppResource extends BaseResource
{
    protected function getResourceData(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'platform' => $this->platform,
            'build_status' => $this->build_status,
        ];
    }
}
```

## Domain Grouping

Resources are organized under `Resources/Api/{Domain}/`:

```
Resources/Api/
├── BaseResource.php         # Abstract template method
├── Account/
│   ├── UserResource.php
│   └── LoginResource.php
└── App/
    ├── AppResource.php
    └── AppDetailResource.php
```

## BaseResource Template

```php
abstract class BaseResource extends JsonResource
{
    public static $wrap = null;  // No outer wrapper on single resources

    abstract protected function getResourceData(): array;

    public function toArray(Request $request): array
    {
        return $this->getResourceData();
    }
}
```

## Schema Chain

Schema is defined on the Resource class with `#[OA\Schema]`, referencing Model schemas via `allOf`:

```
Controller response refs → Resource #[OA\Schema] → Model #[OA\Schema] (allOf)
```

Never define properties inline in Resources when a Model schema exists. Use `allOf` to reference the Model schema and only add resource-specific computed fields.

## Detail vs List Resources
- `AppResource` — list/summary view
- `AppDetailResource` — full detail with relationships

## Usage in Controllers

```php
// Single resource (returns JSON directly)
return new AppDetailResource($app);

// Collection
return AppResource::collection($apps);
```

## DO / DON'T
- DO: Extend `BaseResource` and implement `getResourceData()`
- DO: Add `#[OA\Schema]` with `allOf` refs to Model schemas
- DO: Create separate resources for list vs detail views
- DO: Use `::collection()` for multiple items
- DON'T: Return raw arrays or models directly
- DON'T: Expose internal model attributes without transformation
- DON'T: Define property schemas inline when a Model schema exists
