<?php

namespace Database\Factories;

use App\Models\App;
use App\Models\AppMetric;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AppMetric>
 */
class AppMetricFactory extends Factory
{
    protected $model = AppMetric::class;

    public function definition(): array
    {
        return [
            'app_id' => App::factory(),
            'country_code' => 'us',
            'date' => now()->toDateString(),
            'rating' => 4.5,
            'rating_count' => 100,
            'is_available' => true,
        ];
    }
}
