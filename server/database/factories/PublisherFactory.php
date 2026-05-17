<?php

namespace Database\Factories;

use App\Enums\Platform;
use App\Models\Publisher;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Publisher>
 */
class PublisherFactory extends Factory
{
    protected $model = Publisher::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'platform' => Platform::Ios,
            'external_id' => (string) fake()->unique()->numberBetween(100000, 999999999),
            'url' => fake()->url(),
        ];
    }

    public function android(): static
    {
        return $this->state(fn () => [
            'platform' => Platform::Android,
            'external_id' => urlencode(fake()->company()),
        ]);
    }
}
