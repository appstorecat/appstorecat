<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Run DatabaseSeeder (CountrySeeder + StoreCategorySeeder) after each
     * migrate:fresh so apps.origin_country_code and app_metrics.country_code
     * FK constraints can be satisfied in tests.
     */
    protected bool $seed = true;

    /**
     * Belt-and-braces guard. phpunit.xml sets DB_DATABASE=appstorecat_testing
     * with force="true", but Laravel's artisan test bootstrap has overridden
     * that flag in the past, dropping every row out of the live appstorecat
     * schema during a RefreshDatabase run. Fail loudly here if anything else
     * is wired up.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $db = DB::connection()->getDatabaseName();
        if ($db !== 'appstorecat_testing') {
            throw new RuntimeException(
                "Tests must run against appstorecat_testing, not '{$db}'. "
                .'Check phpunit.xml force="true" attributes and .env.testing.'
            );
        }
    }

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
