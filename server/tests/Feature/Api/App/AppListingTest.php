<?php

declare(strict_types=1);

use App\Enums\Platform;
use App\Jobs\Sync\SyncAppJob;
use App\Models\App;
use App\Models\AppVersion;
use App\Models\StoreListing;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
});

it('returns the existing store listing for the requested locale', function () {
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

    $listing = StoreListing::factory()->create([
        'app_id' => $app->id,
        'version_id' => $version->id,
        'locale' => 'tr',
        'title' => 'Türkçe Başlık',
    ]);

    $response = $this->getJson('/api/v1/apps/ios/389801252/listing?country_code=tr&locale=tr');

    $response->assertOk()
        ->assertJsonPath('id', $listing->id)
        ->assertJsonPath('locale', 'tr')
        ->assertJsonPath('title', 'Türkçe Başlık');

    Queue::assertNotPushed(SyncAppJob::class);
});

it('returns 404 and queues a sync job when no listing exists for the locale', function () {
    $user = createAuthenticatedUser();

    $app = App::factory()->create([
        'platform' => Platform::Ios,
        'external_id' => '389801252',
        'last_synced_at' => null,
    ]);
    $user->apps()->attach($app->id);

    $response = $this->getJson('/api/v1/apps/ios/389801252/listing?country_code=tr&locale=tr');

    $response->assertStatus(404);
    Queue::assertPushed(SyncAppJob::class);
});

it('returns 404 when a listing exists for a different locale only', function () {
    $user = createAuthenticatedUser();

    $app = App::factory()->create([
        'platform' => Platform::Ios,
        'external_id' => '389801252',
        'last_synced_at' => null,
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
    ]);

    $response = $this->getJson('/api/v1/apps/ios/389801252/listing?country_code=tr&locale=tr');

    $response->assertStatus(404);
    Queue::assertPushed(SyncAppJob::class);
});

it('returns 404 when the app does not exist', function () {
    createAuthenticatedUser();

    $this->getJson('/api/v1/apps/ios/com.does.not.exist/listing?country_code=tr&locale=tr')
        ->assertStatus(404);
});

it('validates country_code and locale are required', function () {
    createAuthenticatedUser();

    App::factory()->create([
        'platform' => Platform::Ios,
        'external_id' => '389801252',
    ]);

    $this->getJson('/api/v1/apps/ios/389801252/listing')
        ->assertStatus(422)
        ->assertJsonValidationErrors(['country_code', 'locale']);
});

it('validates country_code refers to a known country', function () {
    createAuthenticatedUser();

    App::factory()->create([
        'platform' => Platform::Ios,
        'external_id' => '389801252',
    ]);

    $this->getJson('/api/v1/apps/ios/389801252/listing?country_code=xx&locale=tr')
        ->assertStatus(422)
        ->assertJsonValidationErrors(['country_code']);
});

it('rejects unauthenticated listing requests', function () {
    App::factory()->create([
        'platform' => Platform::Ios,
        'external_id' => '389801252',
    ]);

    $this->getJson('/api/v1/apps/ios/389801252/listing?country_code=tr&locale=tr')
        ->assertStatus(401);
});
