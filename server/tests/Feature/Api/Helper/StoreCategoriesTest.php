<?php

declare(strict_types=1);

use App\Models\StoreCategory;

it('rejects store-categories listing when unauthenticated', function () {
    $this->getJson('/api/v1/store-categories')->assertStatus(401);
});

it('returns store categories seeded by StoreCategorySeeder', function () {
    createAuthenticatedUser();

    $response = $this->getJson('/api/v1/store-categories');

    $response->assertOk()
        ->assertJsonStructure([
            ['id', 'name', 'slug', 'platform'],
        ]);

    expect(count($response->json()))->toBeGreaterThan(0);
});

it('filters categories by platform=ios', function () {
    createAuthenticatedUser();

    $response = $this->getJson('/api/v1/store-categories?platform=ios');

    $response->assertOk();

    $platforms = collect($response->json())->pluck('platform')->unique()->values()->all();
    expect($platforms)->toBe(['ios']);
});

it('filters categories by platform=android', function () {
    createAuthenticatedUser();

    $response = $this->getJson('/api/v1/store-categories?platform=android');

    $response->assertOk();

    $platforms = collect($response->json())->pluck('platform')->unique()->values()->all();
    expect($platforms)->toBe(['android']);
});

it('rejects an unknown platform value', function () {
    createAuthenticatedUser();

    $this->getJson('/api/v1/store-categories?platform=windows')
        ->assertStatus(422)
        ->assertJsonValidationErrors(['platform']);
});

it('rejects an unknown type value', function () {
    createAuthenticatedUser();

    $this->getJson('/api/v1/store-categories?type=bogus')
        ->assertStatus(422)
        ->assertJsonValidationErrors(['type']);
});

it('orders categories alphabetically by name', function () {
    createAuthenticatedUser();

    $response = $this->getJson('/api/v1/store-categories?platform=ios');

    $names = collect($response->json())->pluck('name')->values()->all();
    $sorted = $names;
    usort($sorted, fn ($a, $b) => strcasecmp($a, $b));

    expect($names)->toBe($sorted);
});

it('returns only categories matching the given platform when both exist', function () {
    createAuthenticatedUser();

    $iosCount = StoreCategory::platform('ios')->count();
    $androidCount = StoreCategory::platform('android')->count();

    expect($iosCount)->toBeGreaterThan(0)
        ->and($androidCount)->toBeGreaterThan(0);

    $iosResponse = $this->getJson('/api/v1/store-categories?platform=ios');
    expect(count($iosResponse->json()))->toBe($iosCount);

    $androidResponse = $this->getJson('/api/v1/store-categories?platform=android');
    expect(count($androidResponse->json()))->toBe($androidCount);
});
