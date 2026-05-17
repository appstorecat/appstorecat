<?php

namespace Database\Factories;

use App\Models\App;
use App\Models\SyncStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SyncStatus>
 */
class SyncStatusFactory extends Factory
{
    protected $model = SyncStatus::class;

    public function definition(): array
    {
        return [
            'app_id' => App::factory(),
            'status' => SyncStatus::STATUS_QUEUED,
        ];
    }
}
