<?php

declare(strict_types=1);

use App\Enums\CompetitorRelationship;
use App\Enums\Platform;
use App\Models\App;
use App\Models\AppCompetitor;
use App\Models\User;

it('tracks an app and returns 204', function () {
    $user = createAuthenticatedUser();

    $app = App::factory()->create([
        'platform' => Platform::Ios,
        'external_id' => '389801252',
    ]);

    $response = $this->postJson('/api/v1/apps/ios/389801252/track');

    $response->assertNoContent();
    $this->assertDatabaseHas('user_apps', [
        'user_id' => $user->id,
        'app_id' => $app->id,
    ]);
});

it('is idempotent: re-tracking the same app does not duplicate user_apps', function () {
    $user = createAuthenticatedUser();

    $app = App::factory()->create([
        'platform' => Platform::Ios,
        'external_id' => '389801252',
    ]);

    $this->postJson('/api/v1/apps/ios/389801252/track')->assertNoContent();
    $this->postJson('/api/v1/apps/ios/389801252/track')->assertNoContent();

    expect($user->apps()->where('apps.id', $app->id)->count())->toBe(1);
});

it('returns 404 when tracking an app that does not exist', function () {
    createAuthenticatedUser();

    $this->postJson('/api/v1/apps/ios/com.does.not.exist/track')->assertStatus(404);
});

it('untracks an app and returns 204', function () {
    $user = createAuthenticatedUser();

    $app = App::factory()->create([
        'platform' => Platform::Ios,
        'external_id' => '389801252',
    ]);
    $user->apps()->attach($app->id);

    $response = $this->deleteJson('/api/v1/apps/ios/389801252/track');

    $response->assertNoContent();
    $this->assertDatabaseMissing('user_apps', [
        'user_id' => $user->id,
        'app_id' => $app->id,
    ]);
});

it('untrack deletes the user\'s competitor mappings for the app', function () {
    $user = createAuthenticatedUser();

    $app = App::factory()->create([
        'platform' => Platform::Ios,
        'external_id' => '389801252',
    ]);
    $user->apps()->attach($app->id);

    $competitorA = App::factory()->create(['platform' => Platform::Ios]);
    $competitorB = App::factory()->create(['platform' => Platform::Ios]);

    AppCompetitor::create([
        'user_id' => $user->id,
        'app_id' => $app->id,
        'competitor_app_id' => $competitorA->id,
        'relationship' => CompetitorRelationship::Direct,
    ]);
    AppCompetitor::create([
        'user_id' => $user->id,
        'app_id' => $app->id,
        'competitor_app_id' => $competitorB->id,
        'relationship' => CompetitorRelationship::Indirect,
    ]);

    $this->deleteJson('/api/v1/apps/ios/389801252/track')->assertNoContent();

    expect(AppCompetitor::where('user_id', $user->id)->where('app_id', $app->id)->count())
        ->toBe(0);
});

it('untrack does not affect other users\' competitor mappings for the same app', function () {
    $user = createAuthenticatedUser();
    $otherUser = User::factory()->create();

    $app = App::factory()->create([
        'platform' => Platform::Ios,
        'external_id' => '389801252',
    ]);
    $user->apps()->attach($app->id);
    $otherUser->apps()->attach($app->id);

    $competitor = App::factory()->create(['platform' => Platform::Ios]);

    AppCompetitor::create([
        'user_id' => $user->id,
        'app_id' => $app->id,
        'competitor_app_id' => $competitor->id,
        'relationship' => CompetitorRelationship::Direct,
    ]);
    $otherMapping = AppCompetitor::create([
        'user_id' => $otherUser->id,
        'app_id' => $app->id,
        'competitor_app_id' => $competitor->id,
        'relationship' => CompetitorRelationship::Direct,
    ]);

    $this->deleteJson('/api/v1/apps/ios/389801252/track')->assertNoContent();

    expect(AppCompetitor::where('user_id', $user->id)->count())->toBe(0);
    expect(AppCompetitor::find($otherMapping->id))->not->toBeNull();
});

it('untrack is idempotent when the user was never tracking the app', function () {
    createAuthenticatedUser();

    App::factory()->create([
        'platform' => Platform::Ios,
        'external_id' => '389801252',
    ]);

    $this->deleteJson('/api/v1/apps/ios/389801252/track')->assertNoContent();
});

it('rejects unauthenticated track requests', function () {
    App::factory()->create([
        'platform' => Platform::Ios,
        'external_id' => '389801252',
    ]);

    $this->postJson('/api/v1/apps/ios/389801252/track')->assertStatus(401);
});

it('rejects unauthenticated untrack requests', function () {
    App::factory()->create([
        'platform' => Platform::Ios,
        'external_id' => '389801252',
    ]);

    $this->deleteJson('/api/v1/apps/ios/389801252/track')->assertStatus(401);
});
