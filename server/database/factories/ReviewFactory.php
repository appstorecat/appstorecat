<?php

namespace Database\Factories;

use App\Models\App;
use App\Models\Review;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Review>
 */
class ReviewFactory extends Factory
{
    public function definition(): array
    {
        return [
            'app_id' => App::factory(),
            'country_code' => fake()->randomElement(['US', 'TR', 'DE', 'FR', 'GB']),
            'external_id' => (string) fake()->unique()->randomNumber(9),
            'author' => fake()->userName(),
            'title' => fake()->sentence(4),
            'body' => fake()->paragraph(),
            'rating' => fake()->numberBetween(1, 5),
            'review_date' => fake()->dateTimeBetween('-1 year')->format('Y-m-d'),
        ];
    }
}
