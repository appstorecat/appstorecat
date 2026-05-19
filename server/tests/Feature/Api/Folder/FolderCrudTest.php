<?php

declare(strict_types=1);

use App\Models\App;
use App\Models\Folder;
use App\Models\User;

it('returns the authenticated user\'s folders with apps_count and sort_order DESC', function () {
    $user = createAuthenticatedUser();

    $folderA = Folder::factory()->for($user)->create([
        'name' => 'Productivity',
        'color' => 'blue',
        'sort_order' => 5,
    ]);
    $folderB = Folder::factory()->for($user)->create([
        'name' => 'Games',
        'color' => 'red',
        'sort_order' => 10,
    ]);

    $response = $this->getJson('/api/v1/folders');

    $response->assertOk()
        ->assertJsonCount(2)
        // sort_order DESC, so folderB (10) comes first
        ->assertJsonPath('0.id', $folderB->id)
        ->assertJsonPath('0.name', 'Games')
        ->assertJsonPath('0.color', 'red')
        ->assertJsonStructure([
            ['id', 'name', 'color', 'sort_order', 'apps_count', 'created_at', 'updated_at'],
        ])
        ->assertJsonPath('1.id', $folderA->id)
        ->assertJsonPath('1.name', 'Productivity');
});

it('does not return folders owned by other users', function () {
    $user = createAuthenticatedUser();
    $other = User::factory()->create();

    $mine = Folder::factory()->for($user)->create(['name' => 'Mine']);
    Folder::factory()->for($other)->create(['name' => 'Theirs']);

    $response = $this->getJson('/api/v1/folders');

    $response->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.id', $mine->id);
});

it('creates a folder and returns 201 with FolderResource shape', function () {
    $user = createAuthenticatedUser();

    $response = $this->postJson('/api/v1/folders', [
        'name' => 'Virtual Number',
        'color' => 'green',
        'sort_order' => 3,
    ]);

    $response->assertCreated()
        ->assertJsonPath('name', 'Virtual Number')
        ->assertJsonPath('color', 'green')
        ->assertJsonPath('sort_order', 3)
        ->assertJsonPath('apps_count', 0);

    $this->assertDatabaseHas('folders', [
        'user_id' => $user->id,
        'name' => 'Virtual Number',
        'color' => 'green',
        'sort_order' => 3,
    ]);
});

it('rejects folder creation without a name', function () {
    createAuthenticatedUser();

    $response = $this->postJson('/api/v1/folders', [
        'color' => 'green',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['name']);
});

it('rejects folder creation with an invalid color', function () {
    createAuthenticatedUser();

    $response = $this->postJson('/api/v1/folders', [
        'name' => 'Some Folder',
        'color' => 'rainbow',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['color']);
});

it('rejects duplicate folder names for the same user', function () {
    $user = createAuthenticatedUser();

    Folder::factory()->for($user)->create(['name' => 'Duplicate']);

    $response = $this->postJson('/api/v1/folders', [
        'name' => 'Duplicate',
        'color' => 'blue',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['name']);
});

it('allows the same folder name across different users', function () {
    $user = createAuthenticatedUser();
    $other = User::factory()->create();

    Folder::factory()->for($other)->create(['name' => 'Shared Name']);

    $response = $this->postJson('/api/v1/folders', [
        'name' => 'Shared Name',
        'color' => 'amber',
    ]);

    $response->assertCreated();
    $this->assertDatabaseHas('folders', [
        'user_id' => $user->id,
        'name' => 'Shared Name',
    ]);
});

it('renames a folder', function () {
    $user = createAuthenticatedUser();
    $folder = Folder::factory()->for($user)->create(['name' => 'Old Name', 'color' => 'red']);

    $response = $this->patchJson("/api/v1/folders/{$folder->id}", [
        'name' => 'New Name',
    ]);

    $response->assertOk()
        ->assertJsonPath('id', $folder->id)
        ->assertJsonPath('name', 'New Name')
        ->assertJsonPath('color', 'red');

    $this->assertDatabaseHas('folders', ['id' => $folder->id, 'name' => 'New Name']);
});

it('updates folder color', function () {
    $user = createAuthenticatedUser();
    $folder = Folder::factory()->for($user)->create(['name' => 'Folder', 'color' => 'red']);

    $response = $this->patchJson("/api/v1/folders/{$folder->id}", [
        'color' => 'purple',
    ]);

    $response->assertOk()->assertJsonPath('color', 'purple');
    $this->assertDatabaseHas('folders', ['id' => $folder->id, 'color' => 'purple']);
});

it('updates folder sort_order', function () {
    $user = createAuthenticatedUser();
    $folder = Folder::factory()->for($user)->create(['sort_order' => 0]);

    $response = $this->patchJson("/api/v1/folders/{$folder->id}", [
        'sort_order' => 42,
    ]);

    $response->assertOk()->assertJsonPath('sort_order', 42);
    $this->assertDatabaseHas('folders', ['id' => $folder->id, 'sort_order' => 42]);
});

it('returns 404 when patching another user\'s folder (no info leak)', function () {
    createAuthenticatedUser();
    $other = User::factory()->create();
    $folder = Folder::factory()->for($other)->create(['name' => 'Theirs']);

    $response = $this->patchJson("/api/v1/folders/{$folder->id}", [
        'name' => 'Hacked',
    ]);

    $response->assertStatus(404);
    $this->assertDatabaseHas('folders', ['id' => $folder->id, 'name' => 'Theirs']);
});

it('deletes a folder and returns 204', function () {
    $user = createAuthenticatedUser();
    $folder = Folder::factory()->for($user)->create();

    $response = $this->deleteJson("/api/v1/folders/{$folder->id}");

    $response->assertNoContent();
    $this->assertDatabaseMissing('folders', ['id' => $folder->id]);
});

it('nulls user_apps.folder_id when its folder is deleted', function () {
    $user = createAuthenticatedUser();
    $folder = Folder::factory()->for($user)->create();
    $app = App::factory()->create();

    $user->apps()->attach($app->id, ['folder_id' => $folder->id]);

    $this->deleteJson("/api/v1/folders/{$folder->id}")->assertNoContent();

    // App is still tracked, but folder_id is now NULL.
    $this->assertDatabaseHas('user_apps', [
        'user_id' => $user->id,
        'app_id' => $app->id,
        'folder_id' => null,
    ]);
});

it('returns 404 when deleting another user\'s folder', function () {
    createAuthenticatedUser();
    $other = User::factory()->create();
    $folder = Folder::factory()->for($other)->create();

    $response = $this->deleteJson("/api/v1/folders/{$folder->id}");

    $response->assertStatus(404);
    $this->assertDatabaseHas('folders', ['id' => $folder->id]);
});

it('rejects unauthenticated folder index requests', function () {
    $this->getJson('/api/v1/folders')->assertStatus(401);
});

it('rejects unauthenticated folder create requests', function () {
    $this->postJson('/api/v1/folders', [
        'name' => 'X',
        'color' => 'blue',
    ])->assertStatus(401);
});

it('rejects unauthenticated folder update requests', function () {
    $user = User::factory()->create();
    $folder = Folder::factory()->for($user)->create();

    $this->patchJson("/api/v1/folders/{$folder->id}", [
        'name' => 'X',
    ])->assertStatus(401);
});

it('rejects unauthenticated folder delete requests', function () {
    $user = User::factory()->create();
    $folder = Folder::factory()->for($user)->create();

    $this->deleteJson("/api/v1/folders/{$folder->id}")->assertStatus(401);
});
