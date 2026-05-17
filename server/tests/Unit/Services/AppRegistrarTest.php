<?php

declare(strict_types=1);

use App\Enums\Platform;
use App\Models\App;
use App\Models\User;
use App\Services\AppRegistrar;

beforeEach(function () {
    $this->registrar = new AppRegistrar;
});

it('creates a new App row when ensureExists is called with a new (external_id, platform) pair', function () {
    expect(App::count())->toBe(0);

    $app = $this->registrar->ensureExists('389801252', Platform::Ios);

    expect($app)->toBeInstanceOf(App::class)
        ->and($app->external_id)->toBe('389801252')
        ->and($app->platform)->toBe(Platform::Ios)
        ->and($app->exists)->toBeTrue();

    expect(App::count())->toBe(1);
});

it('is idempotent — calling ensureExists twice with the same (external_id, platform) returns the same row', function () {
    $first = $this->registrar->ensureExists('389801252', Platform::Ios);
    $second = $this->registrar->ensureExists('389801252', Platform::Ios);

    expect($second->id)->toBe($first->id);
    expect(App::count())->toBe(1);
});

it('treats the same external_id on a different platform as a distinct app', function () {
    $ios = $this->registrar->ensureExists('com.example.app', Platform::Ios);
    $android = $this->registrar->ensureExists('com.example.app', Platform::Android);

    expect($ios->id)->not->toBe($android->id);
    expect(App::count())->toBe(2);
});

it('register() attaches the app to the user via the user_apps pivot', function () {
    $user = User::factory()->create();

    $app = $this->registrar->register($user, '389801252', Platform::Ios);

    expect($app->isTrackedBy($user))->toBeTrue();
    expect($user->apps()->count())->toBe(1);
    expect($user->apps()->first()->id)->toBe($app->id);
});

it('register() is idempotent — second call does not duplicate the pivot row', function () {
    $user = User::factory()->create();

    $this->registrar->register($user, '389801252', Platform::Ios);
    $this->registrar->register($user, '389801252', Platform::Ios);

    expect($user->apps()->count())->toBe(1);
    expect(App::count())->toBe(1);
});

it('register() reuses the catalog row created by ensureExists (does not duplicate apps)', function () {
    $user = User::factory()->create();

    $existing = $this->registrar->ensureExists('389801252', Platform::Ios);
    $registered = $this->registrar->register($user, '389801252', Platform::Ios);

    expect($registered->id)->toBe($existing->id);
    expect(App::count())->toBe(1);
    expect($user->apps()->count())->toBe(1);
});
