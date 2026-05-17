<?php

namespace Database\Factories;

use App\Models\App;
use App\Models\AppVersion;
use App\Models\StoreListingChange;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StoreListingChange>
 */
class StoreListingChangeFactory extends Factory
{
    protected $model = StoreListingChange::class;

    public function definition(): array
    {
        return [
            'app_id' => App::factory(),
            'version_id' => AppVersion::factory(),
            'locale' => 'en-US',
            'field_changed' => 'description',
            'old_value' => 'Old description',
            'new_value' => 'New description',
            'detected_at' => now(),
        ];
    }
}
