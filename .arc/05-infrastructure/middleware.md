# Middleware Conventions

## Registration
Middleware registered in `bootstrap/app.php`.

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->api(append: [
        SetLocaleFromHeader::class,
        LogApiRequest::class,
    ]);
})
```

No `app/Http/Kernel.php` — Laravel 12+ uses `bootstrap/app.php`.

## Current Middleware

### SetLocaleFromHeader
Reads the `X-Language` header from the request and sets the application locale accordingly.

### LogApiRequest
Logs API request and response data for debugging and auditing. Redacts sensitive fields (passwords, tokens).

### auth:sanctum
Laravel Sanctum bearer token authentication. Applied to protected route groups in `routes/api.php`.

## Writing New Middleware

```php
class MyMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        // Before request
        $response = $next($request);
        // After request
        return $response;
    }
}
```

## API Middleware Stack
The API middleware stack in `bootstrap/app.php`:
1. `throttle:api` — Rate limiting
2. `SetLocaleFromHeader` — Locale from X-Language header
3. `LogApiRequest` — Request/response logging

## Rules
- Register in `bootstrap/app.php`, not a kernel
- Feature-flag expensive middleware
- Always redact sensitive data before logging
