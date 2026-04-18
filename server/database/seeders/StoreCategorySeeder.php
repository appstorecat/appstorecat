<?php

namespace Database\Seeders;

use App\Models\StoreCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class StoreCategorySeeder extends Seeder
{
    public function run(): void
    {
        $data = json_decode(file_get_contents(database_path('data/store_categories.json')), true);

        foreach (['ios', 'android'] as $platform) {
            $this->seedPlatform($platform, $data[$platform]);
        }
    }

    /**
     * @param  array{apps: array<int,array<string,mixed>>, games: array<int,array<string,mixed>>}  $payload
     */
    private function seedPlatform(string $platform, array $payload): void
    {
        foreach ($payload['apps'] as $cat) {
            $this->upsert($platform, $cat, 'app');
        }

        $gamesParent = StoreCategory::platform($platform)
            ->where('external_id', $platform === 'ios' ? '6014' : 'GAME')
            ->first();

        foreach ($payload['games'] as $cat) {
            $parentId = $gamesParent?->id;

            if (! empty($cat['parent_key'])) {
                $parent = StoreCategory::platform($platform)
                    ->where('external_id', $cat['parent_key'])
                    ->first();
                $parentId = $parent?->id ?? $parentId;
            }

            $this->upsert($platform, $cat, 'game', $parentId);
        }
    }

    /**
     * @param  array<string,mixed>  $cat
     */
    private function upsert(string $platform, array $cat, string $type, ?int $parentId = null): void
    {
        StoreCategory::updateOrCreate(
            [
                'platform' => StoreCategory::normalizePlatform($platform),
                'external_id' => $cat['external_id'] !== null ? (string) $cat['external_id'] : null,
            ],
            [
                'name' => $cat['name'],
                'slug' => Str::slug($cat['name']),
                'type' => $type,
                'parent_id' => $parentId,
            ],
        );
    }
}
