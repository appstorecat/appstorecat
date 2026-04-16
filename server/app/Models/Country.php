<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use OpenApi\Attributes as OA;

/**
 * @property string $code
 * @property string $name
 * @property string $emoji
 * @property bool $is_active_ios
 * @property bool $is_active_android
 * @property int $priority
 * @property array|null $ios_languages
 * @property array|null $ios_cross_localizable
 * @property array|null $android_languages
 */
#[OA\Schema(
    schema: 'Country',
    required: ['code', 'name', 'emoji'],
    properties: [
        new OA\Property(property: 'code', type: 'string', example: 'us'),
        new OA\Property(property: 'name', type: 'string', example: 'United States'),
        new OA\Property(property: 'emoji', type: 'string', example: '🇺🇸'),
    ],
)]
class Country extends Model
{
    protected $table = 'countries';

    protected $primaryKey = 'code';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['code', 'name', 'emoji', 'is_active_ios', 'is_active_android', 'priority', 'ios_languages', 'ios_cross_localizable', 'android_languages'];

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActiveForPlatform(Builder $query, string $platform): Builder
    {
        return $query->where($platform === 'ios' ? 'is_active_ios' : 'is_active_android', true);
    }

    protected function casts(): array
    {
        return [
            'is_active_ios' => 'boolean',
            'is_active_android' => 'boolean',
            'ios_languages' => 'array',
            'ios_cross_localizable' => 'array',
            'android_languages' => 'array',
        ];
    }
}
