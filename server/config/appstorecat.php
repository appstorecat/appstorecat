<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Connectors
    |--------------------------------------------------------------------------
    */

    'connectors' => [
        'appstore' => [
            'base_url' => env('APPSTORE_API_URL'),
            'timeout' => env('APPSTORE_TIMEOUT', 30),
            'throttle' => [
                'sync_jobs' => env('APPSTORE_THROTTLE_SYNC_JOBS', 5),
                'chart_jobs' => env('APPSTORE_THROTTLE_CHART_JOBS', 24),
            ],
        ],
        'gplay' => [
            'base_url' => env('GPLAY_API_URL'),
            'timeout' => env('GPLAY_TIMEOUT', 30),
            'throttle' => [
                'sync_jobs' => env('GPLAY_THROTTLE_SYNC_JOBS', 5),
                'chart_jobs' => env('GPLAY_THROTTLE_CHART_JOBS', 37),
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Country
    |--------------------------------------------------------------------------
    */

    'default_country' => 'us',

    /*
    |--------------------------------------------------------------------------
    | Sync
    |--------------------------------------------------------------------------
    */

    'sync' => [
        'ios' => [
            'tracked_app_sync_enabled' => env('SYNC_IOS_TRACKED_ENABLED', true),
            'tracked_app_refresh_hours' => env('SYNC_IOS_TRACKED_REFRESH_HOURS', 24),
            'discovery_app_sync_enabled' => env('SYNC_IOS_DISCOVERY_ENABLED', true),
            'discovery_app_refresh_hours' => env('SYNC_IOS_DISCOVERY_REFRESH_HOURS', 24),
            'reviews_enabled' => env('SYNC_IOS_REVIEWS_ENABLED', true),
        ],
        'android' => [
            'tracked_app_sync_enabled' => env('SYNC_ANDROID_TRACKED_ENABLED', true),
            'tracked_app_refresh_hours' => env('SYNC_ANDROID_TRACKED_REFRESH_HOURS', 24),
            'discovery_app_sync_enabled' => env('SYNC_ANDROID_DISCOVERY_ENABLED', true),
            'discovery_app_refresh_hours' => env('SYNC_ANDROID_DISCOVERY_REFRESH_HOURS', 24),
            'reviews_enabled' => env('SYNC_ANDROID_REVIEWS_ENABLED', true),
        ],
        'retry_attempts' => 3,
        'retry_delay' => [30, 60, 120],
    ],

    /*
    |--------------------------------------------------------------------------
    | Discover
    |--------------------------------------------------------------------------
    |
    | Control which sources can create new apps in the database.
    | When disabled, existing apps are still returned — only new
    | app creation is blocked for that source.
    |
    */

    'discover' => [
        'ios' => [
            'on_unknown' => env('DISCOVER_IOS_ON_UNKNOWN', true),
            'on_search' => env('DISCOVER_IOS_ON_SEARCH', true),
            'on_trending' => env('DISCOVER_IOS_ON_TRENDING', true),
            'on_publisher_apps' => env('DISCOVER_IOS_ON_PUBLISHER_APPS', true),
            'on_register' => env('DISCOVER_IOS_ON_REGISTER', true),
            'on_import' => env('DISCOVER_IOS_ON_IMPORT', true),
            'on_similar' => env('DISCOVER_IOS_ON_SIMILAR', true),
            'on_category' => env('DISCOVER_IOS_ON_CATEGORY', true),
            'on_direct_visit' => env('DISCOVER_IOS_ON_DIRECT_VISIT', true),
        ],
        'android' => [
            'on_unknown' => env('DISCOVER_ANDROID_ON_UNKNOWN', true),
            'on_search' => env('DISCOVER_ANDROID_ON_SEARCH', true),
            'on_trending' => env('DISCOVER_ANDROID_ON_TRENDING', true),
            'on_publisher_apps' => env('DISCOVER_ANDROID_ON_PUBLISHER_APPS', true),
            'on_register' => env('DISCOVER_ANDROID_ON_REGISTER', true),
            'on_import' => env('DISCOVER_ANDROID_ON_IMPORT', true),
            'on_similar' => env('DISCOVER_ANDROID_ON_SIMILAR', true),
            'on_category' => env('DISCOVER_ANDROID_ON_CATEGORY', true),
            'on_direct_visit' => env('DISCOVER_ANDROID_ON_DIRECT_VISIT', true),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Charts
    |--------------------------------------------------------------------------
    */

    'charts' => [
        'ios' => [
            'daily_sync_enabled' => env('CHARTS_IOS_DAILY_SYNC_ENABLED', true),
        ],
        'android' => [
            'daily_sync_enabled' => env('CHARTS_ANDROID_DAILY_SYNC_ENABLED', true),
        ],
    ],

];
