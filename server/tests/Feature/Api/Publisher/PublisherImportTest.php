<?php

declare(strict_types=1);

use App\Enums\Platform;
use App\Models\App;
use App\Models\Publisher;

it('imports and tracks the given apps for the authenticated user', function () {
    $user = createAuthenticatedUser();

    $publisher = Publisher::factory()->create([
        'platform' => Platform::Ios,
        'external_id' => '389801255',
    ]);

    // Apps must already exist in the catalog — the request blocks
    // unverified IDs from being registered.
    $appA = App::factory()->for($publisher)->create([
        'platform' => Platform::Ios,
        'external_id' => 'app-a',
    ]);
    $appB = App::factory()->for($publisher)->create([
        'platform' => Platform::Ios,
        'external_id' => 'app-b',
    ]);

    $response = $this->postJson('/api/v1/publishers/ios/389801255/import', [
        'external_ids' => ['app-a', 'app-b'],
    ]);

    $response->assertNoContent();

    expect($user->apps()->pluck('apps.id')->all())
        ->toContain($appA->id, $appB->id);
});

it('is idempotent — re-importing the same app does not duplicate the track', function () {
    $user = createAuthenticatedUser();

    Publisher::factory()->create([
        'platform' => Platform::Ios,
        'external_id' => '111',
    ]);
    $app = App::factory()->create([
        'platform' => Platform::Ios,
        'external_id' => 'idempotent',
    ]);
    $user->apps()->attach($app->id);

    $this->postJson('/api/v1/publishers/ios/111/import', [
        'external_ids' => ['idempotent'],
    ])->assertNoContent();

    expect($user->apps()->where('apps.id', $app->id)->count())->toBe(1);
});

it('rejects import when external_ids is missing', function () {
    createAuthenticatedUser();

    Publisher::factory()->create(['platform' => Platform::Ios, 'external_id' => '111']);

    $response = $this->postJson('/api/v1/publishers/ios/111/import', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['external_ids']);
});

it('rejects import when external_ids is empty', function () {
    createAuthenticatedUser();

    Publisher::factory()->create(['platform' => Platform::Ios, 'external_id' => '111']);

    $response = $this->postJson('/api/v1/publishers/ios/111/import', [
        'external_ids' => [],
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['external_ids']);
});

it('rejects import when more than 50 ids are submitted', function () {
    createAuthenticatedUser();

    Publisher::factory()->create(['platform' => Platform::Ios, 'external_id' => '111']);

    $ids = [];
    for ($i = 0; $i < 51; $i++) {
        $ids[] = "id-{$i}";
    }

    $response = $this->postJson('/api/v1/publishers/ios/111/import', [
        'external_ids' => $ids,
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['external_ids']);
});

it('rejects import when an external_id is not yet discovered in the catalog', function () {
    createAuthenticatedUser();

    Publisher::factory()->create(['platform' => Platform::Ios, 'external_id' => '111']);

    $response = $this->postJson('/api/v1/publishers/ios/111/import', [
        'external_ids' => ['never-seen'],
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['external_ids']);
});

it('only matches apps on the requested platform', function () {
    createAuthenticatedUser();

    Publisher::factory()->android()->create(['external_id' => 'studio']);

    // App exists, but on iOS — the android import must reject it.
    App::factory()->create([
        'platform' => Platform::Ios,
        'external_id' => 'cross-platform-id',
    ]);

    $response = $this->postJson('/api/v1/publishers/android/studio/import', [
        'external_ids' => ['cross-platform-id'],
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['external_ids']);
});

it('imports android apps onto the android queue when platform=android', function () {
    $user = createAuthenticatedUser();

    Publisher::factory()->android()->create(['external_id' => 'studio']);

    $app = App::factory()->android()->create(['external_id' => 'com.example.x']);

    $response = $this->postJson('/api/v1/publishers/android/studio/import', [
        'external_ids' => ['com.example.x'],
    ]);

    $response->assertNoContent();

    expect($user->apps()->where('apps.id', $app->id)->exists())->toBeTrue();
});

it('rejects unauthenticated import requests', function () {
    Publisher::factory()->create(['platform' => Platform::Ios, 'external_id' => '111']);

    $this->postJson('/api/v1/publishers/ios/111/import', [
        'external_ids' => ['foo'],
    ])->assertStatus(401);
});
