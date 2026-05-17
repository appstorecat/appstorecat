<?php

declare(strict_types=1);

use App\Enums\Platform;
use App\Models\App;
use App\Models\User;

it('returns the authenticated user\'s tracked apps', function () {
    $user = createAuthenticatedUser();

    $tracked = App::factory()->create(['display_name' => 'Tracked App']);
    $other = App::factory()->create(['display_name' => 'Untracked App']);
    $user->apps()->attach($tracked->id);

    $response = $this->getJson('/api/v1/apps');

    $response->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.id', $tracked->id)
        ->assertJsonPath('0.name', 'Tracked App');
});

it('filters tracked apps by platform=ios', function () {
    $user = createAuthenticatedUser();

    $ios = App::factory()->create(['platform' => Platform::Ios, 'display_name' => 'iOS App']);
    $android = App::factory()->android()->create(['display_name' => 'Android App']);

    $user->apps()->attach([$ios->id, $android->id]);

    $response = $this->getJson('/api/v1/apps?platform=ios');

    $response->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.id', $ios->id);
});

it('filters tracked apps by platform=android', function () {
    $user = createAuthenticatedUser();

    $ios = App::factory()->create(['platform' => Platform::Ios, 'display_name' => 'iOS App']);
    $android = App::factory()->android()->create(['display_name' => 'Android App']);

    $user->apps()->attach([$ios->id, $android->id]);

    $response = $this->getJson('/api/v1/apps?platform=android');

    $response->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.id', $android->id);
});

it('filters tracked apps by search term against display_name', function () {
    $user = createAuthenticatedUser();

    $instagram = App::factory()->create(['display_name' => 'Instagram']);
    $twitter = App::factory()->create(['display_name' => 'Twitter']);

    $user->apps()->attach([$instagram->id, $twitter->id]);

    $response = $this->getJson('/api/v1/apps?search=insta');

    $response->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.id', $instagram->id);
});

it('filters tracked apps by search term against external_id', function () {
    $user = createAuthenticatedUser();

    $matched = App::factory()->create([
        'display_name' => 'Generic',
        'external_id' => 'com.unique.match.foo',
    ]);
    $unmatched = App::factory()->create([
        'display_name' => 'Other',
        'external_id' => 'com.other.bar',
    ]);

    $user->apps()->attach([$matched->id, $unmatched->id]);

    $response = $this->getJson('/api/v1/apps?search=unique.match');

    $response->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.id', $matched->id);
});

it('rejects unauthenticated app list requests', function () {
    $this->getJson('/api/v1/apps')->assertStatus(401);
});

it('does not return apps tracked by other users', function () {
    $user = createAuthenticatedUser();
    $otherUser = User::factory()->create();

    $mine = App::factory()->create(['display_name' => 'Mine']);
    $theirs = App::factory()->create(['display_name' => 'Theirs']);

    $user->apps()->attach($mine->id);
    $otherUser->apps()->attach($theirs->id);

    $response = $this->getJson('/api/v1/apps');

    $response->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.id', $mine->id);
});
