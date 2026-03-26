<?php

namespace Database\Factories;

use App\Enums\Platform;
use App\Models\App;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<App>
 */
class AppFactory extends Factory
{
    public function definition(): array
    {
        return [
            'platform' => Platform::Ios,
            'external_id' => 'com.'.fake()->domainWord().'.'.fake()->domainWord(),
            'is_free' => true,
        ];
    }

    public function android(): static
    {
        return $this->state(fn () => [
            'platform' => Platform::Android,
            'external_id' => 'com.'.fake()->domainWord().'.'.fake()->domainWord(),
        ]);
    }
}
