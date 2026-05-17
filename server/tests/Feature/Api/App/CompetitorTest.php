<?php

declare(strict_types=1);

use App\Enums\CompetitorRelationship;
use App\Enums\Platform;
use App\Models\App;
use App\Models\AppCompetitor;
use App\Models\User;
use Illuminate\Database\QueryException;

/**
 * Tests for the per-app Competitor endpoints:
 *
 *   GET    /api/v1/apps/{platform}/{externalId}/competitors
 *   POST   /api/v1/apps/{platform}/{externalId}/competitors
 *   DELETE /api/v1/apps/{platform}/{externalId}/competitors/{competitor}
 *
 * Mappings are per-user (user_id, app_id, competitor_app_id) unique.
 * The subject app must be tracked by the caller; the competitor app does NOT.
 */

/* -----------------------------------------------------------------------------
 | Helpers
 |-----------------------------------------------------------------------------*/

/**
 * Build a tracked parent app and a separate competitor app for the given user.
 *
 * @return array{parent: App, competitor: App}
 */
function setupParentAndCompetitor(User $user, Platform $platform = Platform::Ios): array
{
    $parent = App::factory()->create([
        'platform' => $platform,
        'external_id' => 'com.example.parent',
        'display_name' => 'Parent App',
    ]);

    $competitor = App::factory()->create([
        'platform' => $platform,
        'external_id' => 'com.example.rival',
        'display_name' => 'Rival App',
    ]);

    $user->apps()->attach($parent->id);

    return ['parent' => $parent, 'competitor' => $competitor];
}

/* -----------------------------------------------------------------------------
 | GET /apps/{p}/{id}/competitors
 |-----------------------------------------------------------------------------*/

it('lists competitors only for the authenticated user on a tracked app', function () {
    $user = createAuthenticatedUser();
    ['parent' => $parent, 'competitor' => $competitor] = setupParentAndCompetitor($user);

    AppCompetitor::factory()->create([
        'user_id' => $user->id,
        'app_id' => $parent->id,
        'competitor_app_id' => $competitor->id,
        'relationship' => CompetitorRelationship::Direct,
    ]);

    // A different user's mapping for the same parent app must NOT leak in.
    $otherUser = User::factory()->create();
    $otherUser->apps()->attach($parent->id);
    AppCompetitor::factory()->create([
        'user_id' => $otherUser->id,
        'app_id' => $parent->id,
        'competitor_app_id' => $competitor->id,
        'relationship' => CompetitorRelationship::Indirect,
    ]);

    $response = $this->getJson("/api/v1/apps/ios/{$parent->external_id}/competitors");

    // Resource collections are not wrapped — `BaseResource::$wrap = null`
    // overrides the global `JsonResource::withoutWrapping()` so the list sits at
    // the top level of the JSON body.
    $response->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.relationship', 'direct')
        ->assertJsonPath('0.app.external_id', $competitor->external_id);
});

it('returns an empty list when the user has no competitors on a tracked app', function () {
    $user = createAuthenticatedUser();
    ['parent' => $parent] = setupParentAndCompetitor($user);

    $this->getJson("/api/v1/apps/ios/{$parent->external_id}/competitors")
        ->assertOk()
        ->assertJsonCount(0);
});

it('returns 404 when listing competitors for an app the user does not track', function () {
    createAuthenticatedUser();

    $app = App::factory()->create(['external_id' => 'com.example.untracked']);

    $this->getJson("/api/v1/apps/ios/{$app->external_id}/competitors")
        ->assertStatus(404);
});

it('returns 404 when listing competitors for a non-existent app', function () {
    createAuthenticatedUser();

    $this->getJson('/api/v1/apps/ios/com.example.missing/competitors')
        ->assertStatus(404);
});

it('rejects unauthenticated competitor index requests', function () {
    $app = App::factory()->create();

    $this->getJson("/api/v1/apps/ios/{$app->external_id}/competitors")
        ->assertStatus(401);
});

/* -----------------------------------------------------------------------------
 | POST /apps/{p}/{id}/competitors
 |-----------------------------------------------------------------------------*/

it('creates a competitor mapping with competitor_app_id (legacy flow)', function () {
    $user = createAuthenticatedUser();
    ['parent' => $parent, 'competitor' => $competitor] = setupParentAndCompetitor($user);

    $response = $this->postJson("/api/v1/apps/ios/{$parent->external_id}/competitors", [
        'competitor_app_id' => $competitor->id,
        'relationship' => 'indirect',
    ]);

    // Returning a fresh JsonResource for a model that was just `create()`d sets
    // the response to 201 (via Laravel's wasRecentlyCreated heuristic). Single
    // resources are also not `data`-wrapped — see `BaseResource::$wrap = null`.
    $response->assertCreated()
        ->assertJsonPath('relationship', 'indirect')
        ->assertJsonPath('app.external_id', $competitor->external_id);

    $this->assertDatabaseHas('app_competitors', [
        'user_id' => $user->id,
        'app_id' => $parent->id,
        'competitor_app_id' => $competitor->id,
        'relationship' => 'indirect',
    ]);
});

it('defaults relationship to direct when omitted', function () {
    $user = createAuthenticatedUser();
    ['parent' => $parent, 'competitor' => $competitor] = setupParentAndCompetitor($user);

    $this->postJson("/api/v1/apps/ios/{$parent->external_id}/competitors", [
        'competitor_app_id' => $competitor->id,
    ])->assertCreated()
        ->assertJsonPath('relationship', 'direct');
});

it('accepts every CompetitorRelationship enum value', function (string $relationship) {
    $user = createAuthenticatedUser();
    ['parent' => $parent] = setupParentAndCompetitor($user);

    $competitor = App::factory()->create([
        'external_id' => 'com.example.'.$relationship,
    ]);

    $this->postJson("/api/v1/apps/ios/{$parent->external_id}/competitors", [
        'competitor_app_id' => $competitor->id,
        'relationship' => $relationship,
    ])->assertCreated()
        ->assertJsonPath('relationship', $relationship);
})->with(['direct', 'indirect', 'aspiration']);

it('rejects an invalid relationship value', function () {
    $user = createAuthenticatedUser();
    ['parent' => $parent, 'competitor' => $competitor] = setupParentAndCompetitor($user);

    $this->postJson("/api/v1/apps/ios/{$parent->external_id}/competitors", [
        'competitor_app_id' => $competitor->id,
        'relationship' => 'frenemy',
    ])->assertStatus(422)
        ->assertJsonValidationErrors('relationship');
});

it('rejects a competitor_app_id that does not exist', function () {
    $user = createAuthenticatedUser();
    ['parent' => $parent] = setupParentAndCompetitor($user);

    $this->postJson("/api/v1/apps/ios/{$parent->external_id}/competitors", [
        'competitor_app_id' => 999999,
    ])->assertStatus(422)
        ->assertJsonValidationErrors('competitor_app_id');
});

it('requires either competitor_app_id or competitor_external_id', function () {
    $user = createAuthenticatedUser();
    ['parent' => $parent] = setupParentAndCompetitor($user);

    $this->postJson("/api/v1/apps/ios/{$parent->external_id}/competitors", [
        'relationship' => 'direct',
    ])->assertStatus(422);
});

// Regression: commit 454e1a5 ("fix(competitors): allow adding a competitor
// without tracking it first") — historically the API required the competitor
// app to already be tracked by the caller. That restriction was removed.
it('allows adding a competitor that the user does NOT track (regression: 454e1a5)', function () {
    $user = createAuthenticatedUser();
    ['parent' => $parent, 'competitor' => $competitor] = setupParentAndCompetitor($user);

    // Sanity-check: competitor is registered in `apps` but not in user_apps.
    expect($competitor->isTrackedBy($user))->toBeFalse();

    $this->postJson("/api/v1/apps/ios/{$parent->external_id}/competitors", [
        'competitor_app_id' => $competitor->id,
        'relationship' => 'direct',
    ])->assertCreated();

    // The competitor must NOT have been auto-tracked as a side effect.
    expect($competitor->fresh()->isTrackedBy($user))->toBeFalse();

    $this->assertDatabaseHas('app_competitors', [
        'user_id' => $user->id,
        'app_id' => $parent->id,
        'competitor_app_id' => $competitor->id,
    ]);
});

it('creates the competitor app row via competitor_external_id without tracking it', function () {
    $user = createAuthenticatedUser();
    ['parent' => $parent] = setupParentAndCompetitor($user);

    $this->postJson("/api/v1/apps/ios/{$parent->external_id}/competitors", [
        'competitor_external_id' => 'com.example.fresh-rival',
        'relationship' => 'direct',
    ])->assertCreated()
        ->assertJsonPath('app.external_id', 'com.example.fresh-rival');

    $this->assertDatabaseHas('apps', [
        'platform' => Platform::Ios->value,
        'external_id' => 'com.example.fresh-rival',
    ]);

    // Caller's watchlist must not have been touched.
    expect($user->apps()->where('external_id', 'com.example.fresh-rival')->exists())
        ->toBeFalse();
});

it('rejects a duplicate competitor mapping for the same (user, app, competitor)', function () {
    $user = createAuthenticatedUser();
    ['parent' => $parent, 'competitor' => $competitor] = setupParentAndCompetitor($user);

    AppCompetitor::factory()->create([
        'user_id' => $user->id,
        'app_id' => $parent->id,
        'competitor_app_id' => $competitor->id,
    ]);

    // Second insert should fail because of the unique
    // (user_id, app_id, competitor_app_id) constraint. The controller does NOT
    // catch this — it bubbles up as a 500 once Laravel's exception handler runs,
    // so we disable handling here to assert the raw DB exception.
    $this->withoutExceptionHandling();

    expect(fn () => $this->postJson("/api/v1/apps/ios/{$parent->external_id}/competitors", [
        'competitor_app_id' => $competitor->id,
    ]))->toThrow(QueryException::class);

    expect(AppCompetitor::where([
        'user_id' => $user->id,
        'app_id' => $parent->id,
        'competitor_app_id' => $competitor->id,
    ])->count())->toBe(1);
});

it('returns 404 when adding a competitor to an untracked parent app', function () {
    createAuthenticatedUser();

    $parent = App::factory()->create(['external_id' => 'com.example.untracked-parent']);
    $competitor = App::factory()->create(['external_id' => 'com.example.rival']);

    $this->postJson("/api/v1/apps/ios/{$parent->external_id}/competitors", [
        'competitor_app_id' => $competitor->id,
    ])->assertStatus(404);

    $this->assertDatabaseCount('app_competitors', 0);
});

it('rejects unauthenticated competitor store requests', function () {
    $app = App::factory()->create();

    $this->postJson("/api/v1/apps/ios/{$app->external_id}/competitors", [
        'competitor_app_id' => 1,
    ])->assertStatus(401);
});

/* -----------------------------------------------------------------------------
 | DELETE /apps/{p}/{id}/competitors/{competitor}
 |-----------------------------------------------------------------------------*/

it('deletes a competitor mapping owned by the caller', function () {
    $user = createAuthenticatedUser();
    ['parent' => $parent, 'competitor' => $competitor] = setupParentAndCompetitor($user);

    $mapping = AppCompetitor::factory()->create([
        'user_id' => $user->id,
        'app_id' => $parent->id,
        'competitor_app_id' => $competitor->id,
    ]);

    $this->deleteJson("/api/v1/apps/ios/{$parent->external_id}/competitors/{$mapping->id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('app_competitors', ['id' => $mapping->id]);
});

// Per-user scope: User A must not be able to delete User B's mappings, even on
// the same parent app. The controller treats foreign mappings as 404 (not 403)
// to avoid leaking existence of someone else's data.
it('returns 404 when deleting another user\'s competitor mapping', function () {
    $owner = User::factory()->create();
    $parent = App::factory()->create(['external_id' => 'com.example.shared']);
    $competitor = App::factory()->create(['external_id' => 'com.example.rival']);
    $owner->apps()->attach($parent->id);

    $foreign = AppCompetitor::factory()->create([
        'user_id' => $owner->id,
        'app_id' => $parent->id,
        'competitor_app_id' => $competitor->id,
    ]);

    // Attacker tracks the same parent app and tries to delete the foreign row.
    $attacker = createAuthenticatedUser();
    $attacker->apps()->attach($parent->id);

    $this->deleteJson("/api/v1/apps/ios/{$parent->external_id}/competitors/{$foreign->id}")
        ->assertStatus(404);

    $this->assertDatabaseHas('app_competitors', ['id' => $foreign->id]);
});

it('returns 404 when the competitor id does not belong to the given parent app', function () {
    $user = createAuthenticatedUser();
    ['parent' => $parent, 'competitor' => $competitor] = setupParentAndCompetitor($user);

    // Mapping is on a different parent app (still owned by the user).
    $otherParent = App::factory()->create(['external_id' => 'com.example.other-parent']);
    $user->apps()->attach($otherParent->id);

    $mapping = AppCompetitor::factory()->create([
        'user_id' => $user->id,
        'app_id' => $otherParent->id,
        'competitor_app_id' => $competitor->id,
    ]);

    $this->deleteJson("/api/v1/apps/ios/{$parent->external_id}/competitors/{$mapping->id}")
        ->assertStatus(404);

    $this->assertDatabaseHas('app_competitors', ['id' => $mapping->id]);
});

it('returns 404 when deleting a non-existent competitor id', function () {
    $user = createAuthenticatedUser();
    ['parent' => $parent] = setupParentAndCompetitor($user);

    $this->deleteJson("/api/v1/apps/ios/{$parent->external_id}/competitors/999999")
        ->assertStatus(404);
});

it('rejects unauthenticated competitor destroy requests', function () {
    $mapping = AppCompetitor::factory()->create();

    $this->deleteJson(
        "/api/v1/apps/ios/{$mapping->app->external_id}/competitors/{$mapping->id}"
    )->assertStatus(401);
});

/* -----------------------------------------------------------------------------
 | Regression: untrack(parent) cascades the caller's competitor mappings
 |-----------------------------------------------------------------------------*/

it('removes the caller\'s competitor mappings when they untrack the parent app', function () {
    $user = createAuthenticatedUser();
    ['parent' => $parent, 'competitor' => $competitor] = setupParentAndCompetitor($user);

    AppCompetitor::factory()->create([
        'user_id' => $user->id,
        'app_id' => $parent->id,
        'competitor_app_id' => $competitor->id,
    ]);

    // Another user keeps their own mapping on the same parent.
    $otherUser = User::factory()->create();
    $otherUser->apps()->attach($parent->id);
    $otherMapping = AppCompetitor::factory()->create([
        'user_id' => $otherUser->id,
        'app_id' => $parent->id,
        'competitor_app_id' => $competitor->id,
    ]);

    $this->deleteJson("/api/v1/apps/ios/{$parent->external_id}/track")
        ->assertNoContent();

    // Caller's mappings cleared.
    expect(AppCompetitor::where('user_id', $user->id)->where('app_id', $parent->id)->count())
        ->toBe(0);

    // Other user's mapping untouched (per-user scope).
    $this->assertDatabaseHas('app_competitors', ['id' => $otherMapping->id]);
});
