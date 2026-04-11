# Coding Standards

## PSR-12
Enforced via Laravel Pint. Run before every commit:

```bash
./vendor/bin/sail php ./vendor/bin/pint        # Fix
./vendor/bin/sail php ./vendor/bin/pint --test  # Check only
```

## Type Safety

```php
// Full type hints on parameters and return types
public function register(array $validated): App { }
public function __construct(private readonly DnaBuilder $dnaBuilder) {}
```

- Type hints on ALL parameters
- Return types on ALL methods
- `private readonly` for constructor-promoted properties
- Use `?Type` for nullable parameters

## Eloquent Only
- Use Eloquent ORM for all database operations
- NO `DB::select()`, `DB::insert()`, `DB::raw()`
- Exception: `DB::transaction()` is allowed for wrapping operations
- Scopes for reusable query constraints
- Relationships for joins

## PHP 8+ Features
- `match()` instead of `switch`
- Constructor property promotion
- Named arguments for clarity
- `readonly` properties and classes
- Backed enums for fixed value sets
- `?->` nullsafe operator

## DO / DON'T
- DO: Run `./vendor/bin/sail php ./vendor/bin/pint` before completing any task
- DO: Use PHP 8+ features
- DON'T: Use integer literals for HTTP status codes
- DON'T: Use raw SQL or Query Builder
- DON'T: Skip type hints on any parameter or return type
