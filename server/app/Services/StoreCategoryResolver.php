<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\StoreCategory;
use Illuminate\Support\Facades\Log;

class StoreCategoryResolver
{
    /**
     * Resolve a category to its internal id using the scraper-provided external id
     * and/or display name. Categories are static (seeder-sourced). If the pair
     * cannot be matched, an error is logged to the unknown_categories channel and
     * null is returned — callers must tolerate a missing category.
     */
    public function resolveId(string $platform, ?string $externalId, ?string $name, array $context = []): ?int
    {
        $externalId = $externalId !== null ? trim($externalId) : null;
        $name = $name !== null ? trim($name) : null;

        if ($externalId !== null && $externalId !== '') {
            $byExternalId = $this->mapByExternalId($platform)[$externalId] ?? null;
            if ($byExternalId !== null) {
                return $byExternalId;
            }
        }

        if ($name !== null && $name !== '') {
            $byName = $this->mapByName($platform)[mb_strtolower($name)] ?? null;
            if ($byName !== null) {
                return $byName;
            }
        }

        Log::channel('unknown_categories')->error('Unknown store category', [
            'platform' => $platform,
            'external_id' => $externalId,
            'name' => $name,
            'context' => $context,
        ]);

        return null;
    }

    /**
     * @return array<string,int>
     */
    private function mapByExternalId(string $platform): array
    {
        static $cache = [];

        return $cache[$platform] ??= StoreCategory::where('platform', $platform)
            ->whereNotNull('external_id')
            ->pluck('id', 'external_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @return array<string,int>
     */
    private function mapByName(string $platform): array
    {
        static $cache = [];

        if (isset($cache[$platform])) {
            return $cache[$platform];
        }

        $map = [];
        foreach (StoreCategory::where('platform', $platform)->get(['id', 'name']) as $row) {
            $map[mb_strtolower((string) $row->name)] = (int) $row->id;
        }

        return $cache[$platform] = $map;
    }
}
