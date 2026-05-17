<?php

declare(strict_types=1);

use App\Models\AppMetric;
use App\Models\Country;
use Illuminate\Support\Facades\DB;

it('rejects countries listing when unauthenticated', function () {
    $this->getJson('/api/v1/countries')->assertStatus(401);
});

it('returns the list of active countries seeded by CountrySeeder', function () {
    createAuthenticatedUser();

    $response = $this->getJson('/api/v1/countries');

    $response->assertOk()
        ->assertJsonStructure([
            ['code', 'name', 'emoji', 'ios_languages', 'android_languages'],
        ]);

    $codes = collect($response->json())->pluck('code');

    // Sanity check: a handful of well-known iOS/Android markets should be there.
    expect($codes)->toContain('us')
        ->and($codes)->toContain('tr')
        ->and($codes)->toContain('gb');

    // The global sentinel country must never leak through.
    expect($codes)->not->toContain(AppMetric::GLOBAL_COUNTRY);
});

it('excludes countries that are inactive on both platforms', function () {
    createAuthenticatedUser();

    Country::query()->where('code', 'zz')->delete();
    DB::table('countries')->insert([
        'code' => 'zz',
        'name' => 'Inactive Land',
        'emoji' => '??',
        'is_active_ios' => false,
        'is_active_android' => false,
        'priority' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $response = $this->getJson('/api/v1/countries');

    $response->assertOk();

    $codes = collect($response->json())->pluck('code');
    expect($codes)->not->toContain('zz');
});

it('orders countries alphabetically by name', function () {
    createAuthenticatedUser();

    $response = $this->getJson('/api/v1/countries');

    $names = collect($response->json())->pluck('name')->values()->all();
    $sorted = $names;
    // Case-insensitive sort to match MySQL's default utf8mb4 collation.
    usort($sorted, fn ($a, $b) => strcasecmp($a, $b));

    expect($names)->toBe($sorted);
});
