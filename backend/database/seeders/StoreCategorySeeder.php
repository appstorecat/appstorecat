<?php

namespace Database\Seeders;

use App\Models\StoreCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class StoreCategorySeeder extends Seeder
{
    public function run(): void
    {
        $this->seedPlatform('ios', database_path('data/appstore_categories.json'));
        $this->seedPlatform('android', database_path('data/gplay_categories.json'));
    }

    private function seedPlatform(string $platform, string $path): void
    {
        $data = json_decode(file_get_contents($path), true);

        foreach ($data['apps'] as $cat) {
            $this->upsert($platform, $cat, 'app');
        }

        $gamesParent = StoreCategory::where('platform', $platform)
            ->where('slug', 'games')
            ->where('type', 'app')
            ->first();

        foreach ($data['games'] as $cat) {
            $this->upsert($platform, $cat, 'game', $gamesParent?->id);
        }

        if (isset($data['magazines'])) {
            $magazinesParent = StoreCategory::where('platform', $platform)
                ->where('slug', 'magazines-newspapers')
                ->where('type', 'app')
                ->first();

            foreach ($data['magazines'] as $cat) {
                $this->upsert($platform, $cat, 'magazine', $magazinesParent?->id);
            }
        }
    }

    private function upsert(string $platform, array $cat, string $type, ?int $parentId = null): void
    {
        StoreCategory::updateOrCreate(
            [
                'platform' => $platform,
                'slug' => Str::slug($cat['name']),
                'type' => $type,
            ],
            [
                'name' => $cat['name'],
                'external_id' => (string) ($cat['id'] ?? $cat['key'] ?? null),
                'parent_id' => $parentId,
            ],
        );
    }
}
