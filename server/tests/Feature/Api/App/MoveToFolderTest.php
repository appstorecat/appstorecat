<?php

declare(strict_types=1);

use App\Enums\Platform;
use App\Models\App;
use App\Models\Folder;
use App\Models\User;

it('moves a tracked app into a folder and updates the pivot', function () {
    $user = createAuthenticatedUser();
    $folder = Folder::factory()->for($user)->create();

    $app = App::factory()->create([
        'platform' => Platform::Ios,
        'external_id' => '389801252',
    ]);
    $user->apps()->attach($app->id);

    $response = $this->patchJson('/api/v1/apps/ios/389801252/folder', [
        'folder_id' => $folder->id,
    ]);

    $response->assertNoContent();
    $this->assertDatabaseHas('user_apps', [
        'user_id' => $user->id,
        'app_id' => $app->id,
        'folder_id' => $folder->id,
    ]);
});

it('removes a tracked app from its folder when folder_id is null', function () {
    $user = createAuthenticatedUser();
    $folder = Folder::factory()->for($user)->create();

    $app = App::factory()->create([
        'platform' => Platform::Ios,
        'external_id' => '389801252',
    ]);
    $user->apps()->attach($app->id, ['folder_id' => $folder->id]);

    $response = $this->patchJson('/api/v1/apps/ios/389801252/folder', [
        'folder_id' => null,
    ]);

    $response->assertNoContent();
    $this->assertDatabaseHas('user_apps', [
        'user_id' => $user->id,
        'app_id' => $app->id,
        'folder_id' => null,
    ]);
});

it('rejects moving an app into another user\'s folder', function () {
    $user = createAuthenticatedUser();
    $other = User::factory()->create();
    $foreignFolder = Folder::factory()->for($other)->create();

    $app = App::factory()->create([
        'platform' => Platform::Ios,
        'external_id' => '389801252',
    ]);
    $user->apps()->attach($app->id);

    $response = $this->patchJson('/api/v1/apps/ios/389801252/folder', [
        'folder_id' => $foreignFolder->id,
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['folder_id']);

    // Pivot was not changed.
    $this->assertDatabaseHas('user_apps', [
        'user_id' => $user->id,
        'app_id' => $app->id,
        'folder_id' => null,
    ]);
});

it('returns 404 when moving an untracked app', function () {
    $user = createAuthenticatedUser();
    $folder = Folder::factory()->for($user)->create();

    App::factory()->create([
        'platform' => Platform::Ios,
        'external_id' => '389801252',
    ]);
    // Note: user did NOT track this app.

    $response = $this->patchJson('/api/v1/apps/ios/389801252/folder', [
        'folder_id' => $folder->id,
    ]);

    $response->assertStatus(404);
});

it('returns 404 when the app does not exist', function () {
    $user = createAuthenticatedUser();
    $folder = Folder::factory()->for($user)->create();

    $response = $this->patchJson('/api/v1/apps/ios/com.does.not.exist/folder', [
        'folder_id' => $folder->id,
    ]);

    $response->assertStatus(404);
});

it('returns 422 when folder does not exist', function () {
    $user = createAuthenticatedUser();
    $app = App::factory()->create([
        'platform' => Platform::Ios,
        'external_id' => '389801252',
    ]);
    $user->apps()->attach($app->id);

    $response = $this->patchJson('/api/v1/apps/ios/389801252/folder', [
        'folder_id' => 999999,
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['folder_id']);
});
