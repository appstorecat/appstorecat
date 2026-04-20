<?php

use App\Http\Controllers\Api\V1;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    // Auth (public, strict rate limit)
    Route::middleware('throttle:5,1')->group(function () {
        Route::post('auth/register', [V1\Account\AuthController::class, 'register']);
        Route::post('auth/login', [V1\Account\AuthController::class, 'login']);
    });

    // Protected routes
    $rateLimit = app()->environment('local') ? '500,1' : '60,1';
    Route::middleware(['auth:sanctum', "throttle:{$rateLimit}"])->group(function () {

        // Auth
        Route::post('auth/logout', [V1\Account\AuthController::class, 'logout']);
        Route::get('auth/me', [V1\Account\AuthController::class, 'me']);

        // Account
        Route::get('account/profile', [V1\Account\ProfileController::class, 'show']);
        Route::patch('account/profile', [V1\Account\ProfileController::class, 'update']);
        Route::delete('account/profile', [V1\Account\ProfileController::class, 'destroy']);
        Route::put('account/password', [V1\Account\SecurityController::class, 'updatePassword']);

        // API Tokens
        Route::get('account/api-tokens', [V1\Account\ApiTokenController::class, 'index']);
        Route::post('account/api-tokens', [V1\Account\ApiTokenController::class, 'store']);
        Route::delete('account/api-tokens/{tokenId}', [V1\Account\ApiTokenController::class, 'destroy']);

        // Dashboard
        Route::get('dashboard', V1\DashboardController::class);

        // Apps
        Route::get('apps/search', V1\App\AppSearchController::class);
        Route::get('apps', [V1\App\AppController::class, 'index']);
        Route::post('apps', [V1\App\AppController::class, 'store']);

        // App by platform/externalId
        Route::prefix('apps/{platform}/{externalId}')->where(['platform' => 'ios|android', 'externalId' => '[a-zA-Z0-9._]+'])->group(function () {
            Route::get('/', [V1\App\AppController::class, 'show']);
            Route::get('listing', [V1\App\AppController::class, 'listing']);
            Route::post('track', [V1\App\AppController::class, 'track']);
            Route::delete('track', [V1\App\AppController::class, 'untrack']);
            Route::get('competitors', [V1\App\CompetitorController::class, 'index']);
            Route::post('competitors', [V1\App\CompetitorController::class, 'store']);
            Route::delete('competitors/{competitor}', [V1\App\CompetitorController::class, 'destroy']);
            Route::get('keywords', [V1\App\KeywordController::class, 'index']);
            Route::get('keywords/compare', [V1\App\KeywordController::class, 'compare']);
            Route::get('rankings', [V1\App\AppRankingController::class, 'index']);
            Route::post('sync', [V1\App\AppController::class, 'sync']);
            Route::get('sync-status', [V1\App\AppController::class, 'syncStatus']);
        });

        // Competitors (all)
        Route::get('competitors', [V1\App\CompetitorController::class, 'all']);

        // Change Monitor
        Route::get('changes/apps', [V1\ChangeMonitorController::class, 'apps']);
        Route::get('changes/competitors', [V1\ChangeMonitorController::class, 'competitors']);

        // Charts / Trending
        Route::get('charts', [V1\ChartController::class, 'index']);

        // Explorer
        Route::get('explorer/screenshots', [V1\ExplorerController::class, 'screenshots']);
        Route::get('explorer/icons', [V1\ExplorerController::class, 'icons']);

        // Countries
        Route::get('countries', V1\CountryController::class);

        // Store Categories
        Route::get('store-categories', [V1\StoreCategoryController::class, 'index']);

        // Publishers
        Route::get('publishers/search', [V1\PublisherController::class, 'search']);
        Route::get('publishers', [V1\PublisherController::class, 'index']);
        Route::prefix('publishers/{platform}/{externalId}')->where(['platform' => 'ios|android', 'externalId' => '[a-zA-Z0-9._%+ -]+'])->group(function () {
            Route::get('/', [V1\PublisherController::class, 'show']);
            Route::get('store-apps', [V1\PublisherController::class, 'storeApps']);
            Route::post('import', [V1\PublisherController::class, 'import']);
        });
    });
});
