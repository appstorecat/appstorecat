<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OpenApi\Attributes as OA;

/**
 * @property int $id
 * @property int $app_id
 * @property string $version
 * @property Carbon|null $release_date
 * @property string|null $whats_new
 * @property int|null $file_size_bytes
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[OA\Schema(
    schema: 'AppVersion',
    required: ['id', 'version'],
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'version', type: 'string', example: '1.2.4'),
        new OA\Property(property: 'release_date', type: 'string', format: 'date', nullable: true, example: '2026-01-15'),
        new OA\Property(property: 'whats_new', type: 'string', nullable: true),
        new OA\Property(property: 'file_size_bytes', type: 'integer', nullable: true, example: 73822208),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
    ],
)]
#[Fillable([
    'app_id', 'version', 'release_date',
    'whats_new', 'file_size_bytes',
])]
class AppVersion extends Model
{
    protected $table = 'app_versions';

    /**
     * @return BelongsTo<App, $this>
     */
    public function app(): BelongsTo
    {
        return $this->belongsTo(App::class);
    }

    /**
     * @return HasMany<AppMetric, $this>
     */
    public function metrics(): HasMany
    {
        return $this->hasMany(AppMetric::class, 'version_id');
    }

    protected function casts(): array
    {
        return [
            'release_date' => 'date',
        ];
    }
}
