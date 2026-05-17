<?php

namespace Database\Factories;

use App\Models\App;
use App\Models\ChartEntry;
use App\Models\ChartSnapshot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChartEntry>
 */
class ChartEntryFactory extends Factory
{
    protected $model = ChartEntry::class;

    public function definition(): array
    {
        return [
            'trending_chart_id' => ChartSnapshot::factory(),
            'app_id' => App::factory(),
            'rank' => 1,
            'price' => 0,
            'currency' => null,
        ];
    }
}
