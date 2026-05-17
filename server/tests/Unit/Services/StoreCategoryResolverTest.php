<?php

declare(strict_types=1);

use App\Enums\Platform;
use App\Models\StoreCategory;
use App\Services\StoreCategoryResolver;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    $this->resolver = new StoreCategoryResolver;
});

it('returns the matching id when a valid external_id is provided for the platform', function () {
    // Seeded via StoreCategorySeeder: iOS "Photo & Video" -> external_id 6008
    $expected = StoreCategory::platform('ios')->where('external_id', '6008')->firstOrFail();

    $id = $this->resolver->resolveId('ios', '6008', null);

    expect($id)->toBe($expected->id);
});

it('returns the matching id for an external_id even when name is also provided', function () {
    // external_id wins over name when both are present.
    $expected = StoreCategory::platform('ios')->where('external_id', '6005')->firstOrFail();

    $id = $this->resolver->resolveId('ios', '6005', 'some completely wrong name that does not exist');

    expect($id)->toBe($expected->id);
});

it('falls back to name lookup when external_id is null', function () {
    $expected = StoreCategory::platform('ios')
        ->where('name', 'Photo & Video')
        ->firstOrFail();

    $id = $this->resolver->resolveId('ios', null, 'Photo & Video');

    expect($id)->toBe($expected->id);
});

it('falls back to name lookup when external_id is an empty string', function () {
    $expected = StoreCategory::platform('ios')
        ->where('name', 'Productivity')
        ->firstOrFail();

    $id = $this->resolver->resolveId('ios', '', 'Productivity');

    expect($id)->toBe($expected->id);
});

it('matches name case-insensitively and trims whitespace', function () {
    $expected = StoreCategory::platform('ios')
        ->where('name', 'Productivity')
        ->firstOrFail();

    expect($this->resolver->resolveId('ios', null, 'PRODUCTIVITY'))->toBe($expected->id);
    expect($this->resolver->resolveId('ios', null, '  productivity  '))->toBe($expected->id);
});

it('returns null and logs an unknown_categories error when both external_id and name are missing', function () {
    Log::shouldReceive('channel')
        ->with('unknown_categories')
        ->once()
        ->andReturnSelf();
    Log::shouldReceive('error')
        ->once()
        ->with('Unknown store category', Mockery::on(function ($context) {
            return $context['platform'] === 'ios'
                && $context['external_id'] === null
                && $context['name'] === null;
        }));

    $id = $this->resolver->resolveId('ios', null, null);

    expect($id)->toBeNull();
});

it('returns null and logs to unknown_categories when the category cannot be matched', function () {
    Log::shouldReceive('channel')
        ->with('unknown_categories')
        ->once()
        ->andReturnSelf();
    Log::shouldReceive('error')
        ->once()
        ->with('Unknown store category', Mockery::on(function ($context) {
            return $context['platform'] === 'ios'
                && $context['external_id'] === '99999'
                && $context['name'] === 'Not A Real Category';
        }));

    $id = $this->resolver->resolveId(
        platform: 'ios',
        externalId: '99999',
        name: 'Not A Real Category',
        context: ['source' => 'test'],
    );

    expect($id)->toBeNull();
});

it('forwards the caller-provided context array into the unknown_categories log payload', function () {
    Log::shouldReceive('channel')->with('unknown_categories')->once()->andReturnSelf();
    Log::shouldReceive('error')
        ->once()
        ->with('Unknown store category', Mockery::on(function ($context) {
            return ($context['context']['source'] ?? null) === 'AppSyncer'
                && ($context['context']['external_id'] ?? null) === 'com.foo.bar';
        }));

    $this->resolver->resolveId(
        platform: 'android',
        externalId: 'TOTALLY_UNKNOWN_ID',
        name: null,
        context: ['source' => 'AppSyncer', 'external_id' => 'com.foo.bar'],
    );
});

it('uses an in-process static cache so a fresh resolver instance sees the same map', function () {
    $first = $this->resolver->resolveId('ios', '6008', null);

    // Brand new instance — should still resolve from the shared static cache.
    $second = (new StoreCategoryResolver)->resolveId('ios', '6008', null);

    expect($first)->toBe($second)->not->toBeNull();
});

it('caches the external_id map per platform — a row inserted after the first lookup is invisible to the cached map', function () {
    // Prime the cache for android.
    $this->resolver->resolveId('android', 'GAME', null);

    // Insert a brand-new android category AFTER the cache was warmed.
    $fresh = StoreCategory::create([
        'platform' => Platform::Android->value,
        'external_id' => 'TEST_FRESH_CACHE_ID',
        'name' => 'Test Fresh Cache Category',
        'slug' => 'test-fresh-cache-category',
        'type' => 'app',
    ]);

    // Cache was populated before the insert, so the new row should not resolve.
    // To avoid the unknown_categories log polluting the test, fake the log channel.
    Log::shouldReceive('channel')->with('unknown_categories')->andReturnSelf();
    Log::shouldReceive('error');

    $resolved = $this->resolver->resolveId('android', 'TEST_FRESH_CACHE_ID', null);

    expect($resolved)->toBeNull();
    expect($fresh->fresh())->not->toBeNull();
});
