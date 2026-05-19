<?php

declare(strict_types=1);

use App\Enums\Platform;
use App\Models\App;
use App\Models\AppCompetitor;
use App\Models\Folder;
use App\Models\User;

/**
 * Tests for the cross-app competitor index endpoint:
 *
 *   GET /api/v1/competitors
 *
 * Returns a CompetitorGroupResource[] — one group per parent app the caller
 * tracks (only parents that actually have competitors are included). Optional
 * filters: `platform` (ios|android) and `search` (matches parent OR competitor
 * display_name, case-insensitive substring).
 */

/* -----------------------------------------------------------------------------
 | Index — happy path & per-user scope
 |-----------------------------------------------------------------------------*/

it('groups competitors by parent app for the authenticated user', function () {
    $user = createAuthenticatedUser();

    $parentA = App::factory()->create(['external_id' => 'com.example.alpha', 'display_name' => 'Alpha']);
    $parentB = App::factory()->create(['external_id' => 'com.example.bravo', 'display_name' => 'Bravo']);
    $rival1 = App::factory()->create(['external_id' => 'com.rival.one', 'display_name' => 'Rival One']);
    $rival2 = App::factory()->create(['external_id' => 'com.rival.two', 'display_name' => 'Rival Two']);

    $user->apps()->attach([$parentA->id, $parentB->id]);

    AppCompetitor::factory()->create([
        'user_id' => $user->id, 'app_id' => $parentA->id, 'competitor_app_id' => $rival1->id,
    ]);
    AppCompetitor::factory()->create([
        'user_id' => $user->id, 'app_id' => $parentA->id, 'competitor_app_id' => $rival2->id,
    ]);
    AppCompetitor::factory()->create([
        'user_id' => $user->id, 'app_id' => $parentB->id, 'competitor_app_id' => $rival1->id,
    ]);

    $response = $this->getJson('/api/v1/competitors');

    // Resource collections are not wrapped — `BaseResource::$wrap = null`
    // overrides the global `JsonResource::withoutWrapping()` so the array sits
    // at the top level of the JSON body.
    $response->assertOk()->assertJsonCount(2);

    $groups = collect($response->json())->keyBy('parent.external_id');
    expect($groups)->toHaveKeys(['com.example.alpha', 'com.example.bravo']);
    expect($groups['com.example.alpha']['competitors'])->toHaveCount(2);
    expect($groups['com.example.bravo']['competitors'])->toHaveCount(1);
});

it('excludes tracked apps that have no competitor mappings', function () {
    $user = createAuthenticatedUser();

    $withCompetitor = App::factory()->create(['external_id' => 'com.example.with']);
    $empty = App::factory()->create(['external_id' => 'com.example.empty']);
    $rival = App::factory()->create(['external_id' => 'com.example.rival']);

    $user->apps()->attach([$withCompetitor->id, $empty->id]);

    AppCompetitor::factory()->create([
        'user_id' => $user->id,
        'app_id' => $withCompetitor->id,
        'competitor_app_id' => $rival->id,
    ]);

    $response = $this->getJson('/api/v1/competitors');

    $response->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.parent.external_id', 'com.example.with');
});

it('returns an empty list when the user has no tracked apps', function () {
    createAuthenticatedUser();

    $this->getJson('/api/v1/competitors')
        ->assertOk()
        ->assertJsonCount(0);
});

// Per-user scope — User A's mappings must not leak into User B's response,
// even when both track the same parent app.
it('scopes results to the authenticated user only', function () {
    $owner = User::factory()->create();
    $parent = App::factory()->create(['external_id' => 'com.example.shared']);
    $rival = App::factory()->create(['external_id' => 'com.example.rival']);

    $owner->apps()->attach($parent->id);
    AppCompetitor::factory()->create([
        'user_id' => $owner->id,
        'app_id' => $parent->id,
        'competitor_app_id' => $rival->id,
    ]);

    // Caller tracks the same parent but has no mappings of their own.
    $caller = createAuthenticatedUser();
    $caller->apps()->attach($parent->id);

    $this->getJson('/api/v1/competitors')
        ->assertOk()
        ->assertJsonCount(0);
});

/* -----------------------------------------------------------------------------
 | Platform filter
 |-----------------------------------------------------------------------------*/

it('filters groups by platform=ios', function () {
    $user = createAuthenticatedUser();

    $iosParent = App::factory()->create([
        'platform' => Platform::Ios, 'external_id' => 'com.example.ios-parent',
    ]);
    $androidParent = App::factory()->android()->create([
        'external_id' => 'com.example.android-parent',
    ]);
    $iosRival = App::factory()->create([
        'platform' => Platform::Ios, 'external_id' => 'com.example.ios-rival',
    ]);
    $androidRival = App::factory()->android()->create([
        'external_id' => 'com.example.android-rival',
    ]);

    $user->apps()->attach([$iosParent->id, $androidParent->id]);

    AppCompetitor::factory()->create([
        'user_id' => $user->id, 'app_id' => $iosParent->id, 'competitor_app_id' => $iosRival->id,
    ]);
    AppCompetitor::factory()->create([
        'user_id' => $user->id, 'app_id' => $androidParent->id, 'competitor_app_id' => $androidRival->id,
    ]);

    $this->getJson('/api/v1/competitors?platform=ios')
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.parent.external_id', 'com.example.ios-parent');
});

it('filters groups by platform=android', function () {
    $user = createAuthenticatedUser();

    $iosParent = App::factory()->create(['external_id' => 'com.example.ios-parent']);
    $androidParent = App::factory()->android()->create(['external_id' => 'com.example.android-parent']);
    $iosRival = App::factory()->create(['external_id' => 'com.example.ios-rival']);
    $androidRival = App::factory()->android()->create(['external_id' => 'com.example.android-rival']);

    $user->apps()->attach([$iosParent->id, $androidParent->id]);

    AppCompetitor::factory()->create([
        'user_id' => $user->id, 'app_id' => $iosParent->id, 'competitor_app_id' => $iosRival->id,
    ]);
    AppCompetitor::factory()->create([
        'user_id' => $user->id, 'app_id' => $androidParent->id, 'competitor_app_id' => $androidRival->id,
    ]);

    $this->getJson('/api/v1/competitors?platform=android')
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.parent.external_id', 'com.example.android-parent');
});

it('rejects an invalid platform filter', function () {
    createAuthenticatedUser();

    $this->getJson('/api/v1/competitors?platform=windows')
        ->assertStatus(422)
        ->assertJsonValidationErrors('platform');
});

/* -----------------------------------------------------------------------------
 | Search filter
 |-----------------------------------------------------------------------------*/

// When the search term matches the parent app's display_name, the whole group
// is returned (every competitor under that parent).
it('returns the full group when search matches the parent display_name', function () {
    $user = createAuthenticatedUser();

    $parent = App::factory()->create([
        'external_id' => 'com.example.spotify',
        'display_name' => 'Spotify Music',
    ]);
    $rival1 = App::factory()->create(['external_id' => 'com.example.apple', 'display_name' => 'Apple Music']);
    $rival2 = App::factory()->create(['external_id' => 'com.example.deezer', 'display_name' => 'Deezer']);

    $user->apps()->attach($parent->id);

    AppCompetitor::factory()->create([
        'user_id' => $user->id, 'app_id' => $parent->id, 'competitor_app_id' => $rival1->id,
    ]);
    AppCompetitor::factory()->create([
        'user_id' => $user->id, 'app_id' => $parent->id, 'competitor_app_id' => $rival2->id,
    ]);

    $this->getJson('/api/v1/competitors?search=spotify')
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.parent.external_id', 'com.example.spotify')
        ->assertJsonCount(2, '0.competitors');
});

// When the parent does NOT match, only competitors whose own display_name
// matches survive — and the group is dropped entirely if none match.
it('returns only matching competitors when only a competitor display_name matches', function () {
    $user = createAuthenticatedUser();

    $parent = App::factory()->create([
        'external_id' => 'com.example.parent',
        'display_name' => 'Parent App',
    ]);
    $matching = App::factory()->create([
        'external_id' => 'com.example.netflix',
        'display_name' => 'Netflix',
    ]);
    $other = App::factory()->create([
        'external_id' => 'com.example.hulu',
        'display_name' => 'Hulu',
    ]);

    $user->apps()->attach($parent->id);
    AppCompetitor::factory()->create([
        'user_id' => $user->id, 'app_id' => $parent->id, 'competitor_app_id' => $matching->id,
    ]);
    AppCompetitor::factory()->create([
        'user_id' => $user->id, 'app_id' => $parent->id, 'competitor_app_id' => $other->id,
    ]);

    $response = $this->getJson('/api/v1/competitors?search=netflix');

    $response->assertOk()
        ->assertJsonCount(1)
        ->assertJsonCount(1, '0.competitors')
        ->assertJsonPath('0.competitors.0.app.external_id', 'com.example.netflix');
});

it('drops groups whose parent and competitors all fail the search', function () {
    $user = createAuthenticatedUser();

    $parent = App::factory()->create(['external_id' => 'com.example.alpha', 'display_name' => 'Alpha']);
    $rival = App::factory()->create(['external_id' => 'com.example.bravo', 'display_name' => 'Bravo']);

    $user->apps()->attach($parent->id);
    AppCompetitor::factory()->create([
        'user_id' => $user->id, 'app_id' => $parent->id, 'competitor_app_id' => $rival->id,
    ]);

    $this->getJson('/api/v1/competitors?search=nomatchnope')
        ->assertOk()
        ->assertJsonCount(0);
});

it('search match is case-insensitive', function () {
    $user = createAuthenticatedUser();

    $parent = App::factory()->create([
        'external_id' => 'com.example.spotify',
        'display_name' => 'Spotify Music',
    ]);
    $rival = App::factory()->create(['external_id' => 'com.example.rival', 'display_name' => 'Rival']);

    $user->apps()->attach($parent->id);
    AppCompetitor::factory()->create([
        'user_id' => $user->id, 'app_id' => $parent->id, 'competitor_app_id' => $rival->id,
    ]);

    $this->getJson('/api/v1/competitors?search=SPOTIFY')
        ->assertOk()
        ->assertJsonCount(1);
});

it('rejects a search term longer than 100 characters', function () {
    createAuthenticatedUser();

    $this->getJson('/api/v1/competitors?search='.str_repeat('a', 101))
        ->assertStatus(422)
        ->assertJsonValidationErrors('search');
});

/* -----------------------------------------------------------------------------
 | Folder filter
 |-----------------------------------------------------------------------------*/

// Mirror of AppController::index's folder_id contract — the filter targets
// `user_apps.folder_id` on the parent app, so only competitor groups whose
// parent is in the requested folder are returned.
it('filters competitor groups by folder_id', function () {
    $user = createAuthenticatedUser();
    $folder = Folder::factory()->for($user)->create();

    $inFolderParent = App::factory()->create(['external_id' => 'com.example.in-folder']);
    $unsortedParent = App::factory()->create(['external_id' => 'com.example.unsorted']);
    $rival1 = App::factory()->create(['external_id' => 'com.example.rival-one']);
    $rival2 = App::factory()->create(['external_id' => 'com.example.rival-two']);

    $user->apps()->attach([
        $inFolderParent->id => ['folder_id' => $folder->id],
        $unsortedParent->id => ['folder_id' => null],
    ]);

    AppCompetitor::factory()->create([
        'user_id' => $user->id, 'app_id' => $inFolderParent->id, 'competitor_app_id' => $rival1->id,
    ]);
    AppCompetitor::factory()->create([
        'user_id' => $user->id, 'app_id' => $unsortedParent->id, 'competitor_app_id' => $rival2->id,
    ]);

    $this->getJson("/api/v1/competitors?folder_id={$folder->id}")
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.parent.external_id', 'com.example.in-folder');
});

it('returns unassigned parent groups when folder_id=unassigned', function () {
    $user = createAuthenticatedUser();
    $folder = Folder::factory()->for($user)->create();

    $inFolderParent = App::factory()->create(['external_id' => 'com.example.in-folder']);
    $unsortedParent = App::factory()->create(['external_id' => 'com.example.unsorted']);
    $rival1 = App::factory()->create();
    $rival2 = App::factory()->create();

    $user->apps()->attach([
        $inFolderParent->id => ['folder_id' => $folder->id],
        $unsortedParent->id => ['folder_id' => null],
    ]);

    AppCompetitor::factory()->create([
        'user_id' => $user->id, 'app_id' => $inFolderParent->id, 'competitor_app_id' => $rival1->id,
    ]);
    AppCompetitor::factory()->create([
        'user_id' => $user->id, 'app_id' => $unsortedParent->id, 'competitor_app_id' => $rival2->id,
    ]);

    $this->getJson('/api/v1/competitors?folder_id=unassigned')
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.parent.external_id', 'com.example.unsorted');
});

it('rejects an unknown folder_id with 422', function () {
    createAuthenticatedUser();

    $this->getJson('/api/v1/competitors?folder_id=999999')
        ->assertStatus(422)
        ->assertJsonValidationErrors(['folder_id']);
});

/* -----------------------------------------------------------------------------
 | Auth
 |-----------------------------------------------------------------------------*/

it('rejects unauthenticated competitor-all requests', function () {
    $this->getJson('/api/v1/competitors')
        ->assertStatus(401);
});
