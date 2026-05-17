<?php

namespace Database\Factories;

use App\Enums\CompetitorRelationship;
use App\Models\App;
use App\Models\AppCompetitor;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AppCompetitor>
 */
class AppCompetitorFactory extends Factory
{
    protected $model = AppCompetitor::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'app_id' => App::factory(),
            'competitor_app_id' => App::factory(),
            'relationship' => CompetitorRelationship::Direct,
        ];
    }
}
