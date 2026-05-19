<?php

declare(strict_types=1);

use App\Enums\Platform;
use App\Models\App;
use App\Models\AppCompetitor;
use App\Models\Folder;
use App\Models\StoreListingChange;
use App\Models\User;

it('returns store-listing changes for the user\'s competitor apps only', function () {
    $user = createAuthenticatedUser();

    $owned = App::factory()->create(['display_name' => 'Mine']);
    $competitor = App::factory()->create(['display_name' => 'Competitor']);
    $user->apps()->attach($owned->id);

    AppCompetitor::factory()->create([
        'user_id' => $user->id,
        'app_id' => $owned->id,
        'competitor_app_id' => $competitor->id,
    ]);

    StoreListingChange::factory()->create(['app_id' => $owned->id, 'field_changed' => 'description']);
    StoreListingChange::factory()->create(['app_id' => $competitor->id, 'field_changed' => 'description']);

    $this->getJson('/api/v1/changes/competitors')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.app.id', $competitor->id);
});

it('shows an app that is BOTH tracked AND a competitor only on the competitors feed (audit:49)', function () {
    $user = createAuthenticatedUser();

    $tracked = App::factory()->create(['display_name' => 'Tracked']);
    $dualRole = App::factory()->create(['display_name' => 'Dual']);

    $user->apps()->attach([$tracked->id, $dualRole->id]);
    AppCompetitor::factory()->create([
        'user_id' => $user->id,
        'app_id' => $tracked->id,
        'competitor_app_id' => $dualRole->id,
    ]);

    StoreListingChange::factory()->create(['app_id' => $dualRole->id, 'field_changed' => 'title']);

    // Should NOT appear in the tracked-apps feed.
    $this->getJson('/api/v1/changes/apps')
        ->assertOk()
        ->assertJsonCount(0, 'data');

    // SHOULD appear in the competitors feed.
    $this->getJson('/api/v1/changes/competitors')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.app.id', $dualRole->id);
});

it('filters competitors feed by platform', function () {
    $user = createAuthenticatedUser();

    $owned = App::factory()->create();
    $user->apps()->attach($owned->id);

    $iosCompetitor = App::factory()->create(['platform' => Platform::Ios]);
    $androidCompetitor = App::factory()->android()->create();

    AppCompetitor::factory()->create([
        'user_id' => $user->id,
        'app_id' => $owned->id,
        'competitor_app_id' => $iosCompetitor->id,
    ]);
    AppCompetitor::factory()->create([
        'user_id' => $user->id,
        'app_id' => $owned->id,
        'competitor_app_id' => $androidCompetitor->id,
    ]);

    StoreListingChange::factory()->create(['app_id' => $iosCompetitor->id, 'field_changed' => 'description']);
    StoreListingChange::factory()->create(['app_id' => $androidCompetitor->id, 'field_changed' => 'description']);

    $this->getJson('/api/v1/changes/competitors?platform=android')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.app.id', $androidCompetitor->id);
});

it('filters competitors feed by field_changed', function () {
    $user = createAuthenticatedUser();

    $owned = App::factory()->create();
    $competitor = App::factory()->create();
    $user->apps()->attach($owned->id);
    AppCompetitor::factory()->create([
        'user_id' => $user->id,
        'app_id' => $owned->id,
        'competitor_app_id' => $competitor->id,
    ]);

    StoreListingChange::factory()->create(['app_id' => $competitor->id, 'field_changed' => 'description']);
    StoreListingChange::factory()->create(['app_id' => $competitor->id, 'field_changed' => 'whats_new']);

    $this->getJson('/api/v1/changes/competitors?field=whats_new')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.field_changed', 'whats_new');
});

it('filters competitors feed by search against app name', function () {
    $user = createAuthenticatedUser();

    $owned = App::factory()->create();
    $instagram = App::factory()->create(['display_name' => 'Instagram']);
    $twitter = App::factory()->create(['display_name' => 'Twitter']);
    $user->apps()->attach($owned->id);
    AppCompetitor::factory()->create([
        'user_id' => $user->id,
        'app_id' => $owned->id,
        'competitor_app_id' => $instagram->id,
    ]);
    AppCompetitor::factory()->create([
        'user_id' => $user->id,
        'app_id' => $owned->id,
        'competitor_app_id' => $twitter->id,
    ]);

    StoreListingChange::factory()->create(['app_id' => $instagram->id, 'field_changed' => 'description']);
    StoreListingChange::factory()->create(['app_id' => $twitter->id, 'field_changed' => 'description']);

    $this->getJson('/api/v1/changes/competitors?search=insta')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.app.id', $instagram->id);
});

it('paginates 50 per page by default and supports page=2 on competitors feed', function () {
    $user = createAuthenticatedUser();

    $owned = App::factory()->create();
    $competitor = App::factory()->create();
    $user->apps()->attach($owned->id);
    AppCompetitor::factory()->create([
        'user_id' => $user->id,
        'app_id' => $owned->id,
        'competitor_app_id' => $competitor->id,
    ]);

    foreach (range(1, 55) as $i) {
        StoreListingChange::factory()->create([
            'app_id' => $competitor->id,
            'field_changed' => 'description',
            'detected_at' => now()->subMinutes($i),
        ]);
    }

    $this->getJson('/api/v1/changes/competitors')
        ->assertOk()
        ->assertJsonCount(50, 'data')
        ->assertJsonPath('meta.per_page', 50)
        ->assertJsonPath('meta.total', 55);

    $this->getJson('/api/v1/changes/competitors?page=2')
        ->assertOk()
        ->assertJsonCount(5, 'data')
        ->assertJsonPath('meta.current_page', 2);
});

it('does not leak another user\'s competitor changes', function () {
    $user = createAuthenticatedUser();
    $other = User::factory()->create();

    $mineApp = App::factory()->create();
    $theirsApp = App::factory()->create();
    $mineCompetitor = App::factory()->create();
    $theirsCompetitor = App::factory()->create();

    $user->apps()->attach($mineApp->id);
    $other->apps()->attach($theirsApp->id);

    AppCompetitor::factory()->create([
        'user_id' => $user->id,
        'app_id' => $mineApp->id,
        'competitor_app_id' => $mineCompetitor->id,
    ]);
    AppCompetitor::factory()->create([
        'user_id' => $other->id,
        'app_id' => $theirsApp->id,
        'competitor_app_id' => $theirsCompetitor->id,
    ]);

    StoreListingChange::factory()->create(['app_id' => $mineCompetitor->id, 'field_changed' => 'description']);
    StoreListingChange::factory()->create(['app_id' => $theirsCompetitor->id, 'field_changed' => 'description']);

    $this->getJson('/api/v1/changes/competitors')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.app.id', $mineCompetitor->id);
});

it('rejects unauthenticated requests on competitors feed', function () {
    $this->getJson('/api/v1/changes/competitors')->assertStatus(401);
});

/* -----------------------------------------------------------------------------
 | Folder filter — scope follows the *parent* tracked app's folder, so
 | only competitors linked to parents in the requested folder are returned.
 |-----------------------------------------------------------------------------*/

it('filters competitor changes by the parent app\'s folder_id', function () {
    $user = createAuthenticatedUser();
    $folder = Folder::factory()->for($user)->create();

    $inFolderParent = App::factory()->create(['display_name' => 'In Folder']);
    $unsortedParent = App::factory()->create(['display_name' => 'Unsorted']);

    $inFolderRival = App::factory()->create(['display_name' => 'Apple Music']);
    $unsortedRival = App::factory()->create(['display_name' => 'Other Rival']);

    $user->apps()->attach([
        $inFolderParent->id => ['folder_id' => $folder->id],
        $unsortedParent->id => ['folder_id' => null],
    ]);

    AppCompetitor::factory()->create([
        'user_id' => $user->id, 'app_id' => $inFolderParent->id, 'competitor_app_id' => $inFolderRival->id,
    ]);
    AppCompetitor::factory()->create([
        'user_id' => $user->id, 'app_id' => $unsortedParent->id, 'competitor_app_id' => $unsortedRival->id,
    ]);

    StoreListingChange::factory()->create(['app_id' => $inFolderRival->id, 'field_changed' => 'description']);
    StoreListingChange::factory()->create(['app_id' => $unsortedRival->id, 'field_changed' => 'description']);

    $this->getJson("/api/v1/changes/competitors?folder_id={$folder->id}")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.app.id', $inFolderRival->id);
});

it('returns competitors of unassigned parents when folder_id=unassigned', function () {
    $user = createAuthenticatedUser();
    $folder = Folder::factory()->for($user)->create();

    $inFolderParent = App::factory()->create();
    $unsortedParent = App::factory()->create();

    $inFolderRival = App::factory()->create();
    $unsortedRival = App::factory()->create();

    $user->apps()->attach([
        $inFolderParent->id => ['folder_id' => $folder->id],
        $unsortedParent->id => ['folder_id' => null],
    ]);

    AppCompetitor::factory()->create([
        'user_id' => $user->id, 'app_id' => $inFolderParent->id, 'competitor_app_id' => $inFolderRival->id,
    ]);
    AppCompetitor::factory()->create([
        'user_id' => $user->id, 'app_id' => $unsortedParent->id, 'competitor_app_id' => $unsortedRival->id,
    ]);

    StoreListingChange::factory()->create(['app_id' => $inFolderRival->id, 'field_changed' => 'description']);
    StoreListingChange::factory()->create(['app_id' => $unsortedRival->id, 'field_changed' => 'description']);

    $this->getJson('/api/v1/changes/competitors?folder_id=unassigned')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.app.id', $unsortedRival->id);
});

it('rejects an unknown folder_id with 422 on competitor changes', function () {
    createAuthenticatedUser();

    $this->getJson('/api/v1/changes/competitors?folder_id=999999')
        ->assertStatus(422)
        ->assertJsonValidationErrors(['folder_id']);
});
