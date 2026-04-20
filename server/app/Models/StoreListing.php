<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OpenApi\Attributes as OA;

/**
 * @property int $id
 * @property int $app_id
 * @property int|null $version_id
 * @property string $locale
 * @property string $title
 * @property string|null $subtitle
 * @property string $description
 * @property string|null $whats_new
 * @property string|null $icon_url
 * @property array|null $screenshots
 * @property string|null $video_url
 * @property float $price
 * @property string|null $currency
 * @property Carbon|null $fetched_at
 * @property string|null $checksum
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[OA\Schema(
    schema: 'StoreListing',
    required: ['id', 'locale'],
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'version_id', type: 'integer', nullable: true),
        new OA\Property(property: 'locale', type: 'string', example: 'tr'),
        new OA\Property(property: 'title', type: 'string', example: 'Instagram'),
        new OA\Property(property: 'subtitle', type: 'string', nullable: true, example: 'Photos & Videos'),
        new OA\Property(property: 'description', type: 'string'),
        new OA\Property(property: 'whats_new', type: 'string', nullable: true),
        new OA\Property(property: 'icon_url', type: 'string', nullable: true),
        new OA\Property(property: 'screenshots', type: 'array', items: new OA\Items(type: 'string'), nullable: true),
        new OA\Property(property: 'price', type: 'number', format: 'float', example: 6.99),
        new OA\Property(property: 'currency', type: 'string', nullable: true, example: 'USD'),
        new OA\Property(property: 'video_url', type: 'string', nullable: true),
        new OA\Property(property: 'fetched_at', type: 'string', format: 'date-time'),
    ],
)]
#[Fillable([
    'app_id', 'version_id', 'locale', 'title', 'subtitle',
    'description', 'whats_new',
    'screenshots', 'icon_url', 'video_url',
    'price', 'currency',
    'fetched_at', 'checksum',
])]
class StoreListing extends Model
{
    protected $table = 'app_store_listings';

    /**
     * @return BelongsTo<App, $this>
     */
    public function app(): BelongsTo
    {
        return $this->belongsTo(App::class);
    }

    /**
     * @return BelongsTo<AppVersion, $this>
     */
    public function version(): BelongsTo
    {
        return $this->belongsTo(AppVersion::class, 'version_id');
    }

    /**
     * @return array<int, array{url: string, device_type: string, order: int}>
     */
    public function screenshotUrls(): array
    {
        return collect($this->screenshots ?? [])->map(fn ($s, $i) => [
            'url' => $s['url'] ?? '',
            'device_type' => $s['device_type'] ?? 'phone',
            'order' => $s['order'] ?? $i,
        ])->values()->toArray();
    }

    public function getDescriptionLengthAttribute(): int
    {
        return mb_strlen($this->description ?? '');
    }

    protected function casts(): array
    {
        return [
            'screenshots' => 'array',
            'fetched_at' => 'datetime',
        ];
    }
}
