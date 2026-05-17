<?php

declare(strict_types=1);

use App\Enums\Platform;
use App\Models\App;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
});

it('registers a previously discovered app and returns 201 with AppDetailResource', function () {
    $user = createAuthenticatedUser();

    // App must already exist (discovered via search) before it can be registered.
    $app = App::factory()->create([
        'platform' => Platform::Ios,
        'external_id' => '389801252',
        'display_name' => 'Instagram',
    ]);

    $response = $this->postJson('/api/v1/apps', [
        'external_id' => '389801252',
        'platform' => 'ios',
    ]);

    $response->assertCreated()
        ->assertJsonStructure([
            'id', 'name', 'platform', 'external_id', 'is_tracked',
        ])
        ->assertJsonPath('id', $app->id)
        ->assertJsonPath('external_id', '389801252')
        ->assertJsonPath('platform', 'ios')
        ->assertJsonPath('is_tracked', true);
});

it('rejects store request when external_id is missing', function () {
    createAuthenticatedUser();

    $response = $this->postJson('/api/v1/apps', [
        'platform' => 'ios',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['external_id']);
});

it('rejects store request when platform is missing', function () {
    createAuthenticatedUser();

    $response = $this->postJson('/api/v1/apps', [
        'external_id' => '389801252',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['platform']);
});

it('rejects store request when platform is not ios or android', function () {
    createAuthenticatedUser();

    App::factory()->create(['external_id' => '389801252', 'platform' => Platform::Ios]);

    $response = $this->postJson('/api/v1/apps', [
        'external_id' => '389801252',
        'platform' => 'windows',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['platform']);
});

it('rejects store request when the app has not been discovered yet', function () {
    createAuthenticatedUser();

    $response = $this->postJson('/api/v1/apps', [
        'external_id' => 'com.never.discovered',
        'platform' => 'ios',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['external_id']);
});

it('is idempotent when the same app is registered twice (firstOrCreate)', function () {
    $user = createAuthenticatedUser();

    $app = App::factory()->create([
        'platform' => Platform::Ios,
        'external_id' => '389801252',
    ]);

    $this->postJson('/api/v1/apps', [
        'external_id' => '389801252',
        'platform' => 'ios',
    ])->assertCreated();

    $this->postJson('/api/v1/apps', [
        'external_id' => '389801252',
        'platform' => 'ios',
    ])->assertCreated();

    // Same app row, single pivot entry.
    expect(App::where('external_id', '389801252')->count())->toBe(1);
    expect($user->apps()->where('apps.id', $app->id)->count())->toBe(1);
});

it('attaches the app to the user_apps pivot on registration', function () {
    $user = createAuthenticatedUser();

    $app = App::factory()->create([
        'platform' => Platform::Ios,
        'external_id' => '389801252',
    ]);

    expect($user->apps()->where('apps.id', $app->id)->exists())->toBeFalse();

    $this->postJson('/api/v1/apps', [
        'external_id' => '389801252',
        'platform' => 'ios',
    ])->assertCreated();

    expect($user->apps()->where('apps.id', $app->id)->exists())->toBeTrue();
    $this->assertDatabaseHas('user_apps', [
        'user_id' => $user->id,
        'app_id' => $app->id,
    ]);
});

it('rejects unauthenticated store requests', function () {
    $this->postJson('/api/v1/apps', [
        'external_id' => '389801252',
        'platform' => 'ios',
    ])->assertStatus(401);
});
