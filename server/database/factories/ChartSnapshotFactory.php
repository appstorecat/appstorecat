<?php

namespace Database\Factories;

use App\Enums\ChartCollection;
use App\Enums\Platform;
use App\Models\ChartSnapshot;
use App\Models\StoreCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChartSnapshot>
 */
class ChartSnapshotFactory extends Factory
{
    protected $model = ChartSnapshot::class;

    public function definition(): array
    {
        return [
            'platform' => Platform::Ios,
            'collection' => ChartCollection::TopFree,
            'country_code' => 'us',
            'category_id' => StoreCategory::where('platform', 1)->first()?->id,
            'snapshot_date' => now()->toDateString(),
        ];
    }
}
