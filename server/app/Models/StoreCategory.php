<?php

namespace App\Models;

use App\Enums\Platform;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'StoreCategory',
    required: ['id', 'name', 'slug', 'platform'],
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'Photo & Video'),
        new OA\Property(property: 'slug', type: 'string', example: 'photo-video'),
        new OA\Property(property: 'platform', ref: '#/components/schemas/Platform'),
    ],
)]
#[Fillable([
    'name', 'slug', 'platform', 'external_id', 'type', 'parent_id',
])]
/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property Platform $platform
 * @property string|null $external_id
 * @property string|null $type
 * @property int $priority
 * @property int|null $parent_id
 */
class StoreCategory extends Model
{
    protected $table = 'store_categories';

    /**
     * @return HasMany<App, $this>
     */
    public function apps(): HasMany
    {
        return $this->hasMany(App::class, 'category_id');
    }

    /**
     * @return BelongsTo<self, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<self, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public static function findOrCreateByName(string $name, string $platform): self
    {
        return static::firstOrCreate(
            ['platform' => $platform, 'slug' => Str::slug($name), 'type' => 'app'],
            ['name' => $name],
        );
    }

    protected function casts(): array
    {
        return [
            'platform' => Platform::class,
        ];
    }
}
