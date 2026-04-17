<?php

namespace App\Models;

use App\Enums\Platform;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OpenApi\Attributes as OA;
use App\Models\Concerns\HasPlatform;

#[OA\Schema(
    schema: 'Publisher',
    required: ['id', 'name', 'platform'],
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'Instagram, Inc.'),
        new OA\Property(property: 'external_id', type: 'string', nullable: true, example: '389801255'),
        new OA\Property(property: 'platform', ref: '#/components/schemas/Platform'),
        new OA\Property(property: 'url', type: 'string', nullable: true, example: 'https://apps.apple.com/us/developer/instagram-inc/id389801255'),
    ],
)]
#[Fillable([
    'name', 'external_id', 'platform', 'url',
])]
class Publisher extends Model
{
    use HasPlatform;

    protected $table = 'publishers';

    /**
     * @return HasMany<App, $this>
     */
    public function apps(): HasMany
    {
        return $this->hasMany(App::class, 'publisher_id');
    }

    public static function findOrCreateByName(string $name, string $platform, ?string $externalId = null): self
    {
        // Android uses developer name as external_id (URL-encoded for safe linking)
        $resolvedExternalId = $externalId ?? ($platform === 'android' ? urlencode($name) : null);
        $platformValue = self::normalizePlatform($platform);

        if ($resolvedExternalId) {
            return static::firstOrCreate(
                ['platform' => $platformValue, 'external_id' => $resolvedExternalId],
                ['name' => $name],
            );
        }

        return static::firstOrCreate(
            ['platform' => $platformValue, 'name' => $name],
        );
    }

    protected function casts(): array
    {
        return [
            'platform' => \App\Casts\PlatformCast::class,
        ];
    }
}
