<?php

declare(strict_types=1);

use App\Enums\Platform;
use App\Models\App;
use App\Models\Publisher;
use App\Models\User;

it('returns publishers tied to the authenticated user tracked apps', function () {
    $user = createAuthenticatedUser();

    $mine = Publisher::factory()->create(['name' => 'My Publisher']);
    $theirs = Publisher::factory()->create(['name' => 'Their Publisher']);

    $myApp = App::factory()->for($mine)->create();
    $theirApp = App::factory()->for($theirs)->create();

    $user->apps()->attach($myApp->id);

    // Other user tracks the other publisher's app — should not surface.
    $otherUser = User::factory()->create();
    $otherUser->apps()->attach($theirApp->id);

    $response = $this->getJson('/api/v1/publishers');

    $response->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.id', $mine->id)
        ->assertJsonPath('0.name', 'My Publisher');
});

it('rejects unauthenticated index requests', function () {
    $this->getJson('/api/v1/publishers')->assertStatus(401);
});

it('returns an empty list when the user has no tracked apps', function () {
    createAuthenticatedUser();

    // Publisher exists in the system but the user does not track any of its apps.
    $publisher = Publisher::factory()->create();
    App::factory()->for($publisher)->create();

    $response = $this->getJson('/api/v1/publishers');

    $response->assertOk()->assertJsonCount(0);
});

it('orders publishers alphabetically by name', function () {
    $user = createAuthenticatedUser();

    $charlie = Publisher::factory()->create(['name' => 'Charlie Co']);
    $alpha = Publisher::factory()->create(['name' => 'Alpha Co']);
    $bravo = Publisher::factory()->create(['name' => 'Bravo Co']);

    foreach ([$charlie, $alpha, $bravo] as $pub) {
        $app = App::factory()->for($pub)->create();
        $user->apps()->attach($app->id);
    }

    $response = $this->getJson('/api/v1/publishers');

    $response->assertOk()
        ->assertJsonCount(3)
        ->assertJsonPath('0.name', 'Alpha Co')
        ->assertJsonPath('1.name', 'Bravo Co')
        ->assertJsonPath('2.name', 'Charlie Co');
});

it('exposes apps_count scoped to the user tracked apps, not the global publisher catalog', function () {
    $user = createAuthenticatedUser();

    $publisher = Publisher::factory()->create(['name' => 'Shared Publisher']);

    // Two apps the user tracks under this publisher.
    $trackedA = App::factory()->for($publisher)->create();
    $trackedB = App::factory()->for($publisher)->create();
    $user->apps()->attach([$trackedA->id, $trackedB->id]);

    // Three extra apps under the same publisher the user does NOT track.
    App::factory()->for($publisher)->count(3)->create();

    // Apps owned by another user under the same publisher should not inflate count.
    $other = User::factory()->create();
    $otherTracked = App::factory()->for($publisher)->create();
    $other->apps()->attach($otherTracked->id);

    $response = $this->getJson('/api/v1/publishers');

    $response->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.id', $publisher->id)
        // Audit finding: apps_count reflects the user-scoped subset (2), not
        // the global six rows on the publisher.
        ->assertJsonPath('0.apps_count', 2);
});

it('ignores the platform query parameter (controller does not filter by platform)', function () {
    $user = createAuthenticatedUser();

    $iosPublisher = Publisher::factory()->create(['platform' => Platform::Ios, 'name' => 'iOS Publisher']);
    $androidPublisher = Publisher::factory()->android()->create(['name' => 'Android Publisher']);

    $iosApp = App::factory()->for($iosPublisher)->create(['platform' => Platform::Ios]);
    $androidApp = App::factory()->for($androidPublisher)->android()->create();

    $user->apps()->attach([$iosApp->id, $androidApp->id]);

    // Even though the client sends ?platform=ios, the index endpoint
    // returns publishers for both platforms — no platform scoping is
    // applied server-side.
    $response = $this->getJson('/api/v1/publishers?platform=ios');

    $response->assertOk()->assertJsonCount(2);
});

it('returns the platform string for each publisher', function () {
    $user = createAuthenticatedUser();

    $publisher = Publisher::factory()->create(['platform' => Platform::Ios]);
    $app = App::factory()->for($publisher)->create();
    $user->apps()->attach($app->id);

    $response = $this->getJson('/api/v1/publishers');

    $response->assertOk()
        ->assertJsonPath('0.platform', 'ios');
});
