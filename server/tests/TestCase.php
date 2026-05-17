<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Sanctum\Sanctum;

abstract class TestCase extends BaseTestCase
{
    /**
     * Run DatabaseSeeder (CountrySeeder + StoreCategorySeeder) after each
     * migrate:fresh so apps.origin_country_code and app_metrics.country_code
     * FK constraints can be satisfied in tests.
     */
    protected bool $seed = true;

    /**
     * Authenticate a fresh User via Sanctum and return it.
     * Pass an existing user to re-authenticate as that one.
     */
    protected function loginAs(?User $user = null): User
    {
        $user ??= User::factory()->create();
        Sanctum::actingAs($user, ['*']);

        return $user;
    }
}
