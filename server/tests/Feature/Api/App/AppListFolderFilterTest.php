<?php

declare(strict_types=1);

use App\Models\App;
use App\Models\Folder;

it('filters tracked apps by a specific folder_id', function () {
    $user = createAuthenticatedUser();
    $folder = Folder::factory()->for($user)->create();

    $inFolder = App::factory()->create(['display_name' => 'In Folder']);
    $outsideFolder = App::factory()->create(['display_name' => 'Outside Folder']);

    $user->apps()->attach([
        $inFolder->id => ['folder_id' => $folder->id],
        $outsideFolder->id => ['folder_id' => null],
    ]);

    $response = $this->getJson("/api/v1/apps?folder_id={$folder->id}");

    $response->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.id', $inFolder->id);
});

it('returns unassigned tracked apps when folder_id=unassigned', function () {
    $user = createAuthenticatedUser();
    $folder = Folder::factory()->for($user)->create();

    $inFolder = App::factory()->create(['display_name' => 'Sorted']);
    $unsorted = App::factory()->create(['display_name' => 'Unsorted']);

    $user->apps()->attach([
        $inFolder->id => ['folder_id' => $folder->id],
        $unsorted->id => ['folder_id' => null],
    ]);

    $response = $this->getJson('/api/v1/apps?folder_id=unassigned');

    $response->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.id', $unsorted->id);
});

it('returns all tracked apps when no folder filter is provided', function () {
    $user = createAuthenticatedUser();
    $folder = Folder::factory()->for($user)->create();

    $inFolder = App::factory()->create(['display_name' => 'In Folder']);
    $unsorted = App::factory()->create(['display_name' => 'Unsorted']);

    $user->apps()->attach([
        $inFolder->id => ['folder_id' => $folder->id],
        $unsorted->id => ['folder_id' => null],
    ]);

    $response = $this->getJson('/api/v1/apps');

    $response->assertOk()->assertJsonCount(2);
});

it('returns 422 when folder_id does not exist for the user', function () {
    createAuthenticatedUser();

    $response = $this->getJson('/api/v1/apps?folder_id=999999');

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['folder_id']);
});
