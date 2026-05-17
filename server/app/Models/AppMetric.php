<?php

namespace App\Models;

use Database\Factories\AppMetricFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'AppMetric',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'country_code', type: 'string', example: 'us'),
        new OA\Property(property: 'date', type: 'string', format: 'date', example: '2026-04-07'),
        new OA\Property(property: 'rating', type: 'number', format: 'float', example: 4.68),
        new OA\Property(property: 'rating_count', type: 'integer', example: 31),
        new OA\Property(property: 'rating_breakdown', type: 'object', nullable: true),
        new OA\Property(property: 'is_available', type: 'boolean', example: true),
    ],
)]
#[Fillable([
    'app_id', 'version_id', 'country_code', 'date',
    'rating', 'rating_count', 'rating_breakdown', 'is_available',
])]
class AppMetric extends Model
{
    /** @use HasFactory<AppMetricFactory> */
    use HasFactory;

    protected $table = 'app_metrics';

    public const GLOBAL_COUNTRY = 'zz';

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

    protected function casts(): array
    {
        return [
            'rating' => 'decimal:2',
            'rating_breakdown' => 'array',
            'date' => 'date',
            'is_available' => 'boolean',
        ];
    }
}
