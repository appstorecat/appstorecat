# Service Conventions

## Location
`App\Services\{ServiceName}.php`

## Structure

```php
class AppRegistrar
{
    public function register(array $validated): App
    {
        // Business logic here
    }
}

class DnaBuilder
{
    public function build(App $app): void
    {
        // Orchestrate DNA build chain
    }
}
```

## Rules

### Dependency Injection
- Services injected via constructor with `private readonly`
- Let the container auto-resolve (no manual binding needed for concrete classes)

### Database Transactions
Wrap multi-step writes in `DB::transaction()`.

```php
DB::transaction(function () use ($data) {
    $app = App::create([...]);
    $app->storeListing()->create([...]);
    return $app;
});
```

### Error Handling
- Services throw exceptions — let the controller catch them
- Log errors with context data

## DO / DON'T
- DO: Keep services focused on a single domain
- DO: Return models or DTOs from service methods
- DO: Use `DB::transaction()` for multi-step operations
- DON'T: Access Request or Response objects in services
- DON'T: Catch exceptions unless you can handle them meaningfully
