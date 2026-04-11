# Configuration Conventions

## Location
`config/{domain}.php` — one file per domain.

## Rules

- ALL values driven by `env()` with sensible defaults
- Boolean flags for feature toggles: `env('FEATURE_FLAG', false)`
- Group related settings under a single config file

## Environment Variables Pattern
```
{SERVICE}_{SETTING}=value
```

## DO / DON'T
- DO: Use `env()` only in config files
- DO: Provide production-safe defaults
- DO: Use `config('app.name')` in application code
- DON'T: Access `env()` outside config files
- DON'T: Hardcode credentials or URLs
