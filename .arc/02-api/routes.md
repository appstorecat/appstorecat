# Route Conventions

## API Routes
All routes are in `routes/api.php` under the `/v1` prefix.

### Public Routes (no auth)
```php
Route::prefix('v1/auth')->group(function () {
    Route::post('/login', [LoginController::class, 'login']);
    Route::post('/register', [RegisterController::class, 'register']);
});
```

### Protected Routes (auth:sanctum)
```php
Route::prefix('v1')->middleware('auth:sanctum')->group(function () {
    // Auth
    Route::post('/auth/logout', [LoginController::class, 'logout']);
    Route::get('/auth/me', [ProfileController::class, 'me']);

    // Account
    Route::put('/account/profile', [ProfileController::class, 'update']);
    Route::put('/account/password', [PasswordController::class, 'update']);

    // Apps
    Route::get('/apps', [AppController::class, 'index']);
    Route::post('/apps', [AppController::class, 'store']);
    Route::get('/apps/{app}', [AppController::class, 'show']);
    Route::get('/apps/{app}/build-status', [BuildStatusController::class, 'show']);
});
```

## Web Routes
`routes/web.php` only has a catch-all redirect to the frontend SPA.

```php
Route::get('/{any?}', fn () => redirect(config('app.frontend_url')))
    ->where('any', '.*');
```

## Rules
- All API routes under `v1` prefix
- Group by authentication: public vs protected (`auth:sanctum`)
- Use Route Model Binding for model parameters
- No named routes needed (frontend uses Orval-generated API client)
- Version API routes (`v1`, `v2`)

## DO / DON'T
- DO: Version API routes (`v1`, `v2`)
- DO: Group routes by auth requirement
- DO: Use Route Model Binding
- DON'T: Put middleware logic in route files
- DON'T: Add Inertia or web page routes
