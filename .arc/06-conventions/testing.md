# Testing Conventions

## Framework
Pest PHP via Laravel's test runner.

```bash
./vendor/bin/sail test                          # Run all tests
./vendor/bin/sail test --filter=TestName        # Run specific test
./vendor/bin/sail test tests/Feature/SomeTest.php  # Run specific file
```

## Structure

```
tests/
├── Feature/          # Integration tests
│   ├── Jobs/         # Job chain tests
│   ├── Connectors/   # Connector tests
│   └── ...
├── Unit/             # Unit tests
└── Pest.php          # Helpers (createAuthenticatedUser, etc.)
```

## Current State
- Existing Connector and Job tests are kept and passing
- Old Auth/Settings/API tests (from Inertia era) have been removed
- New endpoint tests to be written per endpoint as they are built

## Test Pattern (Pest)

```php
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates an app', function () {
    $user = createAuthenticatedUser();

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/apps', [
            'store_id' => 'com.example.app',
            'platform' => 'android',
        ]);

    $response->assertCreated()
        ->assertJsonStructure(['id', 'name', 'platform']);
});
```

## API Tests

```php
it('returns app details via API', function () {
    $user = createAuthenticatedUser();
    $app = App::factory()->create();

    $response = $this->actingAs($user, 'sanctum')
        ->getJson("/api/v1/apps/{$app->id}");

    $response->assertOk()
        ->assertJsonStructure(['id', 'name', 'platform']);
});
```

## Frontend Tests
Not yet set up. Vitest is planned for the React web app.

## Rules
- `RefreshDatabase` trait for tests that touch the database
- Use `createAuthenticatedUser()` helper from `tests/Pest.php`
- All tests use `actingAs($user, 'sanctum')` (API auth)
- Use `postJson`, `getJson`, etc. (not `post`, `get`)
- Use factories for model creation in tests
- Use `assertOk()`, `assertCreated()`, etc. for status assertions
