# Core Architecture Patterns

## Design Patterns Used

### Service Layer
Thin controllers delegate business logic to service classes.

```php
// Controller - thin, delegates to service
public function __construct(
    private readonly AppRegistrar $appRegistrar,
) {}

public function store(StoreAppRequest $request): AppResource
{
    $app = $this->appRegistrar->register($request->validated());
    return new AppResource($app);
}
```

### Connector Pattern
Data source connectors implement `ConnectorInterface`, return `ConnectorResult` DTO.

```php
// Each connector fetches data from an external source
interface ConnectorInterface
{
    public function fetch(App $app): ConnectorResult;
}

// ConnectorResult DTO wraps the response
class ConnectorResult
{
    public function __construct(
        public readonly bool $success,
        public readonly array $data,
        public readonly ?string $error = null,
    ) {}
}
```

Connectors live in `app/Connectors/`. Each data source (Google Play, iTunes, etc.) has its own connector.

### Job Chain Pattern
DNA build uses `Bus::chain()` with sequential jobs in `app/Jobs/Chain/`.

```php
// BuildDnaJob dispatches a chain
Bus::chain([
    new FetchIdentityJob($app),
    new FetchListingsJob($app),
    new FetchMetricsJob($app),
    new FinalizeDnaBuildJob($app),
])->dispatch();
```

- Each chain job updates `build_status` on the App model
- Jobs are sequential — each depends on the previous
- Chain jobs live in `app/Jobs/Chain/`
- Orchestrator job (`BuildDnaJob`) lives in `app/Jobs/`

### Enum Pattern
Business constants use PHP backed enums, stored as strings in DB.

```php
enum BuildStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';
}

// Model cast
protected function casts(): array
{
    return ['build_status' => BuildStatus::class];
}
```

Enums live in `app/Enums/`. Use enums for all fixed value sets.

### Swagger Schema Chain
OpenAPI documentation follows a layered schema chain:

```
Controller (#[OA\Get/Post/...] with response refs)
  → Resource (#[OA\Schema] with model refs via allOf)
    → Model (#[OA\Schema] with property definitions)
      → Enum (#[OA\Schema] with type + enum values)
```

See `02-api/swagger.md` for full details.

## Directory Organization

```
app/
├── Connectors/                    # Data source connectors
├── Enums/                         # PHP backed enums
├── Http/
│   ├── Controllers/Api/
│   │   ├── BaseController.php     # Swagger global config (@OA\Info, etc.)
│   │   └── V1/
│   │       ├── Account/           # Auth, Profile, Security
│   │       └── App/               # Apps, Search, BuildStatus
│   ├── Middleware/                 # API middleware (locale, logging)
│   ├── Requests/Api/
│   │   ├── Account/               # Login, Register, Profile, Password
│   │   └── App/                   # StoreApp
│   ├── Resources/Api/
│   │   ├── BaseResource.php       # Abstract template method
│   │   ├── Account/               # UserResource, LoginResource
│   │   └── App/                   # AppResource, AppDetailResource, etc.
│   └── Responses/                 # Fortify auth responses
├── Jobs/                          # Orchestrator jobs
│   └── Chain/                     # Sequential chain jobs
├── Models/                        # Eloquent models
├── Services/                      # Business logic services
├── Actions/                       # Fortify actions
├── Concerns/                      # Shared traits
└── Providers/                     # Service providers
```
