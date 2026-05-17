<?php

declare(strict_types=1);

use App\Enums\CompetitorRelationship;
use App\Enums\Platform;
use App\Jobs\Sync\SyncAppJob;
use App\Models\App;
use App\Models\AppCompetitor;
use App\Models\AppVersion;
use App\Models\StoreListing;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
});

it('returns AppDetailResource for an existing app', function () {
    $user = createAuthenticatedUser();

    $app = App::factory()->create([
        'platform' => Platform::Ios,
        'external_id' => '389801252',
        'display_name' => 'Instagram',
        'last_synced_at' => now(),
    ]);
    $user->apps()->attach($app->id);

    $response = $this->getJson('/api/v1/apps/ios/389801252');

    $response->assertOk()
        ->assertJsonPath('id', $app->id)
        ->assertJsonPath('name', 'Instagram')
        ->assertJsonPath('platform', 'ios')
        ->assertJsonPath('external_id', '389801252')
        ->assertJsonPath('is_tracked', true)
        ->assertJsonStructure([
            'id', 'name', 'platform', 'external_id',
            'listings', 'versions', 'changes', 'competitors',
            'is_available', 'is_tracked', 'unavailable_countries',
        ]);
});

it('returns 404 when the app does not exist', function () {
    createAuthenticatedUser();

    $this->getJson('/api/v1/apps/ios/com.does.not.exist')->assertStatus(404);
});

it('loads listings and versions on the response (whenLoaded relations)', function () {
    $user = createAuthenticatedUser();

    $app = App::factory()->create([
        'platform' => Platform::Ios,
        'external_id' => '389801252',
        'last_synced_at' => now(),
    ]);
    $user->apps()->attach($app->id);

    $version = AppVersion::factory()->create([
        'app_id' => $app->id,
        'version' => '1.2.4',
    ]);

    StoreListing::factory()->create([
        'app_id' => $app->id,
        'version_id' => $version->id,
        'locale' => 'en-US',
        'title' => 'Listing Title',
    ]);

    $response = $this->getJson('/api/v1/apps/ios/389801252');

    $response->assertOk()
        ->assertJsonCount(1, 'listings')
        ->assertJsonPath('listings.0.locale', 'en-US')
        ->assertJsonPath('listings.0.title', 'Listing Title')
        ->assertJsonCount(1, 'versions')
        ->assertJsonPath('versions.0.version', '1.2.4');
});

it('returns competitors for a tracked app', function () {
    $user = createAuthenticatedUser();

    $app = App::factory()->create([
        'platform' => Platform::Ios,
        'external_id' => '389801252',
        'last_synced_at' => now(),
    ]);
    $user->apps()->attach($app->id);

    $competitorApp = App::factory()->create([
        'platform' => Platform::Ios,
        'display_name' => 'Competitor',
    ]);

    AppCompetitor::create([
        'user_id' => $user->id,
        'app_id' => $app->id,
        'competitor_app_id' => $competitorApp->id,
        'relationship' => CompetitorRelationship::Direct,
    ]);

    $response = $this->getJson('/api/v1/apps/ios/389801252');

    $response->assertOk()
        ->assertJsonCount(1, 'competitors');
});

it('returns empty competitors when the user does not track the app', function () {
    $user = createAuthenticatedUser();
    $otherUser = User::factory()->create();

    $app = App::factory()->create([
        'platform' => Platform::Ios,
        'external_id' => '389801252',
        'last_synced_at' => now(),
    ]);

    // Other user tracks it and has a competitor mapping.
    $otherUser->apps()->attach($app->id);

    $competitorApp = App::factory()->create(['platform' => Platform::Ios]);
    AppCompetitor::create([
        'user_id' => $otherUser->id,
        'app_id' => $app->id,
        'competitor_app_id' => $competitorApp->id,
        'relationship' => CompetitorRelationship::Direct,
    ]);

    $response = $this->getJson('/api/v1/apps/ios/389801252');

    $response->assertOk()
        ->assertJsonPath('is_tracked', false)
        ->assertJsonCount(0, 'competitors');
});

it('dispatches a sync job when last_synced_at is null', function () {
    $user = createAuthenticatedUser();

    $app = App::factory()->create([
        'platform' => Platform::Ios,
        'external_id' => '389801252',
        'last_synced_at' => null,
    ]);
    $user->apps()->attach($app->id);

    $this->getJson('/api/v1/apps/ios/389801252')->assertOk();

    Queue::assertPushed(SyncAppJob::class);
});

it('does not dispatch a sync job when the app was recently synced', function () {
    $user = createAuthenticatedUser();

    $app = App::factory()->create([
        'platform' => Platform::Ios,
        'external_id' => '389801252',
        'last_synced_at' => now(),
    ]);
    $user->apps()->attach($app->id);

    $this->getJson('/api/v1/apps/ios/389801252')->assertOk();

    Queue::assertNotPushed(SyncAppJob::class);
});

it('rejects unauthenticated show requests', function () {
    App::factory()->create([
        'platform' => Platform::Ios,
        'external_id' => '389801252',
    ]);

    $this->getJson('/api/v1/apps/ios/389801252')->assertStatus(401);
});
