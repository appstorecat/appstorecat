<?php

declare(strict_types=1);

use App\Enums\Platform;
use App\Models\App;
use App\Models\AppCompetitor;
use App\Models\AppVersion;
use App\Models\Folder;
use App\Models\StoreListingChange;
use App\Models\User;

it('returns store-listing changes for the user\'s tracked apps', function () {
    $user = createAuthenticatedUser();

    $tracked = App::factory()->create(['display_name' => 'My Tracked']);
    $other = App::factory()->create(['display_name' => 'Untracked']);
    $user->apps()->attach($tracked->id);

    $version = AppVersion::factory()->create(['app_id' => $tracked->id]);

    StoreListingChange::factory()->create([
        'app_id' => $tracked->id,
        'version_id' => $version->id,
        'field_changed' => 'description',
        'detected_at' => now(),
    ]);
    StoreListingChange::factory()->create([
        'app_id' => $other->id,
        'field_changed' => 'description',
        'detected_at' => now(),
    ]);

    $response = $this->getJson('/api/v1/changes/apps');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.app.id', $tracked->id)
        ->assertJsonPath('data.0.field_changed', 'description');
});

it('filters by platform=ios', function () {
    $user = createAuthenticatedUser();

    $ios = App::factory()->create(['platform' => Platform::Ios, 'display_name' => 'iOS Tracked']);
    $android = App::factory()->android()->create(['display_name' => 'Android Tracked']);
    $user->apps()->attach([$ios->id, $android->id]);

    StoreListingChange::factory()->create(['app_id' => $ios->id, 'field_changed' => 'title']);
    StoreListingChange::factory()->create(['app_id' => $android->id, 'field_changed' => 'title']);

    $response = $this->getJson('/api/v1/changes/apps?platform=ios');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.app.id', $ios->id);
});

it('filters by field_changed', function () {
    $user = createAuthenticatedUser();

    $app = App::factory()->create();
    $user->apps()->attach($app->id);

    StoreListingChange::factory()->create(['app_id' => $app->id, 'field_changed' => 'description']);
    StoreListingChange::factory()->create(['app_id' => $app->id, 'field_changed' => 'title']);
    StoreListingChange::factory()->create(['app_id' => $app->id, 'field_changed' => 'whats_new']);

    $response = $this->getJson('/api/v1/changes/apps?field=title');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.field_changed', 'title');
});

it('filters by search against app display_name', function () {
    $user = createAuthenticatedUser();

    $instagram = App::factory()->create(['display_name' => 'Instagram']);
    $twitter = App::factory()->create(['display_name' => 'Twitter']);
    $user->apps()->attach([$instagram->id, $twitter->id]);

    StoreListingChange::factory()->create(['app_id' => $instagram->id, 'field_changed' => 'description']);
    StoreListingChange::factory()->create(['app_id' => $twitter->id, 'field_changed' => 'description']);

    $response = $this->getJson('/api/v1/changes/apps?search=insta');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.app.id', $instagram->id);
});

it('paginates 50 per page by default and supports page=2', function () {
    $user = createAuthenticatedUser();

    $app = App::factory()->create();
    $user->apps()->attach($app->id);

    // 55 changes, descending by detected_at.
    foreach (range(1, 55) as $i) {
        StoreListingChange::factory()->create([
            'app_id' => $app->id,
            'field_changed' => 'description',
            'detected_at' => now()->subMinutes($i),
        ]);
    }

    $page1 = $this->getJson('/api/v1/changes/apps');
    $page1->assertOk()
        ->assertJsonCount(50, 'data')
        ->assertJsonPath('meta.per_page', 50)
        ->assertJsonPath('meta.total', 55);

    $page2 = $this->getJson('/api/v1/changes/apps?page=2');
    $page2->assertOk()
        ->assertJsonCount(5, 'data')
        ->assertJsonPath('meta.current_page', 2);
});

it('excludes apps that are only competitors (not tracked)', function () {
    $user = createAuthenticatedUser();

    $competitor = App::factory()->create(['display_name' => 'Competitor']);
    // Not attached to user->apps(); only registered as a competitor.
    AppCompetitor::factory()->create([
        'user_id' => $user->id,
        'app_id' => App::factory()->create()->id,
        'competitor_app_id' => $competitor->id,
    ]);

    StoreListingChange::factory()->create(['app_id' => $competitor->id, 'field_changed' => 'description']);

    $this->getJson('/api/v1/changes/apps')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('excludes an app from the tracked feed when it is also a competitor', function () {
    $user = createAuthenticatedUser();

    $tracked = App::factory()->create(['display_name' => 'Owned']);
    $alsoCompetitor = App::factory()->create(['display_name' => 'Both Roles']);

    $user->apps()->attach([$tracked->id, $alsoCompetitor->id]);
    AppCompetitor::factory()->create([
        'user_id' => $user->id,
        'app_id' => $tracked->id,
        'competitor_app_id' => $alsoCompetitor->id,
    ]);

    StoreListingChange::factory()->create(['app_id' => $tracked->id, 'field_changed' => 'description']);
    StoreListingChange::factory()->create(['app_id' => $alsoCompetitor->id, 'field_changed' => 'description']);

    $response = $this->getJson('/api/v1/changes/apps');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.app.id', $tracked->id);
});

it('does not leak changes belonging to other users\' tracked apps', function () {
    $user = createAuthenticatedUser();
    $other = User::factory()->create();

    $mine = App::factory()->create();
    $theirs = App::factory()->create();

    $user->apps()->attach($mine->id);
    $other->apps()->attach($theirs->id);

    StoreListingChange::factory()->create(['app_id' => $mine->id, 'field_changed' => 'description']);
    StoreListingChange::factory()->create(['app_id' => $theirs->id, 'field_changed' => 'description']);

    $this->getJson('/api/v1/changes/apps')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.app.id', $mine->id);
});

it('rejects unauthenticated requests', function () {
    $this->getJson('/api/v1/changes/apps')->assertStatus(401);
});

/* -----------------------------------------------------------------------------
 | Folder filter — scope is `user_apps.folder_id` on each tracked app.
 |-----------------------------------------------------------------------------*/

it('filters tracked changes by folder_id', function () {
    $user = createAuthenticatedUser();
    $folder = Folder::factory()->for($user)->create();

    $inFolder = App::factory()->create(['display_name' => 'In Folder']);
    $unsorted = App::factory()->create(['display_name' => 'Unsorted']);
    $user->apps()->attach([
        $inFolder->id => ['folder_id' => $folder->id],
        $unsorted->id => ['folder_id' => null],
    ]);

    StoreListingChange::factory()->create(['app_id' => $inFolder->id, 'field_changed' => 'description']);
    StoreListingChange::factory()->create(['app_id' => $unsorted->id, 'field_changed' => 'description']);

    $this->getJson("/api/v1/changes/apps?folder_id={$folder->id}")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.app.id', $inFolder->id);
});

it('returns only unassigned tracked changes when folder_id=unassigned', function () {
    $user = createAuthenticatedUser();
    $folder = Folder::factory()->for($user)->create();

    $inFolder = App::factory()->create(['display_name' => 'In Folder']);
    $unsorted = App::factory()->create(['display_name' => 'Unsorted']);
    $user->apps()->attach([
        $inFolder->id => ['folder_id' => $folder->id],
        $unsorted->id => ['folder_id' => null],
    ]);

    StoreListingChange::factory()->create(['app_id' => $inFolder->id, 'field_changed' => 'description']);
    StoreListingChange::factory()->create(['app_id' => $unsorted->id, 'field_changed' => 'description']);

    $this->getJson('/api/v1/changes/apps?folder_id=unassigned')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.app.id', $unsorted->id);
});

it('rejects an unknown folder_id with 422 on tracked changes', function () {
    createAuthenticatedUser();

    $this->getJson('/api/v1/changes/apps?folder_id=999999')
        ->assertStatus(422)
        ->assertJsonValidationErrors(['folder_id']);
});
