<?php

declare(strict_types=1);

use App\Models\App;
use App\Models\AppVersion;
use App\Models\StoreListingChange;
use App\Models\User;
use Illuminate\Support\Facades\DB;

it('rejects dashboard when unauthenticated', function () {
    $this->getJson('/api/v1/dashboard')->assertStatus(401);
});

it('returns zeroed dashboard for a user without any tracked apps', function () {
    createAuthenticatedUser();

    $response = $this->getJson('/api/v1/dashboard');

    $response->assertOk()
        ->assertJsonPath('total_apps', 0)
        ->assertJsonPath('total_versions', 0)
        ->assertJsonPath('total_changes', 0)
        ->assertJsonPath('recent_changes', []);
});

it('aggregates counts for the authenticated user only', function () {
    $user = createAuthenticatedUser();
    $otherUser = User::factory()->create();

    $myApps = App::factory()->count(2)->create();
    $otherApp = App::factory()->create();

    $user->apps()->attach($myApps->pluck('id')->all());
    $otherUser->apps()->attach($otherApp->id);

    foreach ($myApps as $app) {
        AppVersion::factory()->count(2)->create(['app_id' => $app->id]);
        StoreListingChange::factory()->count(3)->create(['app_id' => $app->id]);
    }

    // Noise for the other user — must not be counted.
    AppVersion::factory()->count(5)->create(['app_id' => $otherApp->id]);
    StoreListingChange::factory()->count(5)->create(['app_id' => $otherApp->id]);

    $response = $this->getJson('/api/v1/dashboard');

    $response->assertOk()
        ->assertJsonPath('total_apps', 2)
        ->assertJsonPath('total_versions', 4)
        ->assertJsonPath('total_changes', 6);

    expect(count($response->json('recent_changes')))->toBe(5);
});

it('returns at most 5 recent_changes ordered by detected_at desc', function () {
    $user = createAuthenticatedUser();
    $app = App::factory()->create(['display_name' => 'Test App']);
    $user->apps()->attach($app->id);

    foreach (range(1, 8) as $i) {
        StoreListingChange::factory()->create([
            'app_id' => $app->id,
            'field_changed' => "field-{$i}",
            'detected_at' => now()->subMinutes(10 - $i),
        ]);
    }

    $response = $this->getJson('/api/v1/dashboard');

    $response->assertOk();

    $recent = $response->json('recent_changes');
    expect(count($recent))->toBe(5)
        ->and($recent[0]['field_changed'])->toBe('field-8')
        ->and($recent[4]['field_changed'])->toBe('field-4');

    // Strictly descending by detected_at.
    $timestamps = array_map(fn ($r) => $r['detected_at'], $recent);
    $sorted = $timestamps;
    rsort($sorted);
    expect($timestamps)->toBe($sorted);
});

it('exposes app_name on each recent_changes item (regression)', function () {
    $user = createAuthenticatedUser();
    $app = App::factory()->create(['display_name' => 'My Tracked App']);
    $user->apps()->attach($app->id);

    StoreListingChange::factory()->create([
        'app_id' => $app->id,
        'field_changed' => 'description',
        'locale' => 'en-US',
    ]);

    $response = $this->getJson('/api/v1/dashboard');

    $response->assertOk()
        ->assertJsonPath('recent_changes.0.app_name', 'My Tracked App')
        ->assertJsonPath('recent_changes.0.field_changed', 'description')
        ->assertJsonPath('recent_changes.0.locale', 'en-US');

    $appName = $response->json('recent_changes.0.app_name');
    expect($appName)->not->toBeNull()->toBeString();
});

it('falls back to external_id for app_name when display_name is missing', function () {
    $user = createAuthenticatedUser();
    $app = App::factory()->create([
        'display_name' => null,
        'external_id' => 'fallback-id-1',
    ]);
    $user->apps()->attach($app->id);

    StoreListingChange::factory()->create(['app_id' => $app->id]);

    $response = $this->getJson('/api/v1/dashboard');

    $response->assertOk()
        ->assertJsonPath('recent_changes.0.app_name', 'fallback-id-1');
});

it('eager loads the app relation on recent_changes to avoid N+1', function () {
    $user = createAuthenticatedUser();
    $apps = App::factory()->count(5)->create();
    $user->apps()->attach($apps->pluck('id')->all());

    foreach ($apps as $app) {
        StoreListingChange::factory()->create(['app_id' => $app->id]);
    }

    DB::enableQueryLog();
    DB::flushQueryLog();

    $response = $this->getJson('/api/v1/dashboard');

    $response->assertOk();

    // Count queries that fetch a single app by primary key — those are the
    // tell-tale lazy-loaded `app()` accesses inside the recent_changes map.
    // With `with('app')` we should see at most one batched IN-clause query
    // (which would not match `where `id` =`). Joins that reference
    // `apps`.`id` in their ON clause are excluded by the `where` check.
    $singleAppQueries = collect(DB::getQueryLog())
        ->filter(fn ($q) => str_contains($q['query'], 'from `apps`')
            && ! str_contains($q['query'], 'inner join')
            && str_contains($q['query'], '`apps`.`id` =')
            && ! str_contains($q['query'], '`apps`.`id` in'))
        ->count();

    DB::disableQueryLog();

    expect($singleAppQueries)->toBe(0);
});

it('only includes changes from apps the user tracks', function () {
    $user = createAuthenticatedUser();
    $myApp = App::factory()->create(['display_name' => 'Mine']);
    $otherApp = App::factory()->create(['display_name' => 'Not Mine']);

    $user->apps()->attach($myApp->id);

    StoreListingChange::factory()->create([
        'app_id' => $myApp->id,
        'detected_at' => now()->subMinute(),
    ]);
    StoreListingChange::factory()->create([
        'app_id' => $otherApp->id,
        'detected_at' => now(),
    ]);

    $response = $this->getJson('/api/v1/dashboard');

    $response->assertOk();

    $appNames = collect($response->json('recent_changes'))->pluck('app_name')->all();
    expect($appNames)->toBe(['Mine']);
});
