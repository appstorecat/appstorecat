<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\FolderColor;
use App\Models\Folder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Folder>
 */
class FolderFactory extends Factory
{
    protected $model = Folder::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->unique()->words(2, true),
            'color' => fake()->randomElement(FolderColor::cases())->value,
            'sort_order' => 0,
        ];
    }
}
