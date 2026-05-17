<?php

namespace Database\Factories;

use App\Models\App;
use App\Models\AppVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AppVersion>
 */
class AppVersionFactory extends Factory
{
    protected $model = AppVersion::class;

    public function definition(): array
    {
        return [
            'app_id' => App::factory(),
            'version' => fake()->unique()->numerify('#.#.##'),
            'release_date' => now()->toDateString(),
        ];
    }
}
