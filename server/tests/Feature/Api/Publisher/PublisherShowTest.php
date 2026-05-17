<?php

declare(strict_types=1);

use App\Enums\Platform;
use App\Models\App;
use App\Models\Publisher;
use App\Models\User;

it('returns publisher details with the user tracked apps only', function () {
    $user = createAuthenticatedUser();

    $publisher = Publisher::factory()->create([
        'platform' => Platform::Ios,
        'external_id' => '389801255',
        'name' => 'Meta Platforms, Inc.',
    ]);

    $trackedA = App::factory()->for($publisher)->create(['display_name' => 'Tracked A']);
    $trackedB = App::factory()->for($publisher)->create(['display_name' => 'Tracked B']);
    $untracked = App::factory()->for($publisher)->create(['display_name' => 'Untracked']);

    $user->apps()->attach([$trackedA->id, $trackedB->id]);

    $response = $this->getJson('/api/v1/publishers/ios/389801255');

    $response->assertOk()
        ->assertJsonPath('publisher.id', $publisher->id)
        ->assertJsonPath('publisher.external_id', '389801255')
        ->assertJsonPath('publisher.name', 'Meta Platforms, Inc.')
        ->assertJsonPath('publisher.platform', 'ios')
        ->assertJsonCount(2, 'apps');

    $appIds = collect($response->json('apps'))->pluck('id')->all();
    expect($appIds)->toContain($trackedA->id, $trackedB->id)
        ->and($appIds)->not->toContain($untracked->id);
});

it('returns 404 when the publisher does not exist', function () {
    createAuthenticatedUser();

    $this->getJson('/api/v1/publishers/ios/000000000')
        ->assertStatus(404);
});

it('matches by platform — same external_id on a different platform is a 404', function () {
    createAuthenticatedUser();

    Publisher::factory()->create([
        'platform' => Platform::Ios,
        'external_id' => 'same-id',
    ]);

    $this->getJson('/api/v1/publishers/android/same-id')
        ->assertStatus(404);
});

it('accepts an optional name query parameter without altering the response', function () {
    $user = createAuthenticatedUser();

    $publisher = Publisher::factory()->create([
        'platform' => Platform::Ios,
        'external_id' => '12345',
        'name' => 'Real Name',
    ]);
    $app = App::factory()->for($publisher)->create();
    $user->apps()->attach($app->id);

    $response = $this->getJson('/api/v1/publishers/ios/12345?name=DisplayHint');

    $response->assertOk()
        // The DB row's name is authoritative — the ?name= hint is not
        // reflected in the payload.
        ->assertJsonPath('publisher.name', 'Real Name');
});

it('returns an empty apps array when the user tracks none of the publisher apps', function () {
    createAuthenticatedUser();

    $publisher = Publisher::factory()->create([
        'platform' => Platform::Ios,
        'external_id' => '88888',
    ]);
    App::factory()->for($publisher)->count(3)->create();

    $response = $this->getJson('/api/v1/publishers/ios/88888');

    $response->assertOk()->assertJsonCount(0, 'apps');
});

it('does not leak apps tracked by other users', function () {
    $user = createAuthenticatedUser();
    $other = User::factory()->create();

    $publisher = Publisher::factory()->create([
        'platform' => Platform::Ios,
        'external_id' => 'shared',
    ]);

    $mine = App::factory()->for($publisher)->create();
    $theirs = App::factory()->for($publisher)->create();

    $user->apps()->attach($mine->id);
    $other->apps()->attach($theirs->id);

    $response = $this->getJson('/api/v1/publishers/ios/shared');

    $response->assertOk()
        ->assertJsonCount(1, 'apps')
        ->assertJsonPath('apps.0.id', $mine->id);
});

it('rejects unauthenticated show requests', function () {
    $this->getJson('/api/v1/publishers/ios/12345')->assertStatus(401);
});
