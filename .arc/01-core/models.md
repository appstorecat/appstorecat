# Model Conventions

## Enum Casts
Use PHP backed enums with the `casts()` method.

```php
protected function casts(): array
{
    return [
        'platform' => Platform::class,
        'build_status' => BuildStatus::class,
        'price_model' => PriceModel::class,
    ];
}
```

## Fillable & Guarded
Define explicit `$fillable` for mass-assignable fields.

## Scopes
Named scopes for reusable query constraints.

```php
public function scopeByPlatform(Builder $query, Platform $platform): Builder
{
    return $query->where('platform', $platform);
}
```

## Relationships
Always define return types explicitly.

```php
public function reviews(): HasMany
{
    return $this->hasMany(Review::class);
}

public function storeListing(): HasOne
{
    return $this->hasOne(StoreListing::class);
}

public function competitors(): BelongsToMany
{
    return $this->belongsToMany(App::class, 'app_competitors', 'app_id', 'competitor_id');
}
```

## Accessors & Mutators
Use Laravel's `Attribute` class for accessors/mutators.

```php
protected function fullName(): Attribute
{
    return Attribute::get(fn () => "{$this->name} ({$this->platform->value})");
}
```

## DO / DON'T
- DO: Use enums for all fixed value sets (cast in `casts()` method)
- DO: Define exhaustive `$fillable` and `casts()`
- DO: Use scopes for reusable query constraints
- DO: Eager load relationships to prevent N+1 queries
- DON'T: Use string literals for enum values in queries
- DON'T: Put controller-level logic in models
- DON'T: Use `$casts` property — use `casts()` method (Laravel 11+)
