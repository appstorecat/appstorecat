<?php

namespace Database\Factories;

use App\Models\App;
use App\Models\AppVersion;
use App\Models\StoreListing;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StoreListing>
 */
class StoreListingFactory extends Factory
{
    protected $model = StoreListing::class;

    public function definition(): array
    {
        $title = fake()->words(2, true);

        return [
            'app_id' => App::factory(),
            'version_id' => AppVersion::factory(),
            'locale' => 'en-US',
            'title' => $title,
            'description' => 'Test description',
            'screenshots' => [],
            'price' => 0,
            'currency' => 'USD',
            'fetched_at' => now(),
            'checksum' => md5($title.'|en-US'),
        ];
    }
}
