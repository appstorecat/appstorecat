<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'AppMetric',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'date', type: 'string', format: 'date', example: '2026-04-07'),
        new OA\Property(property: 'rating', type: 'number', format: 'float', example: 4.68),
        new OA\Property(property: 'rating_count', type: 'integer', example: 31),
        new OA\Property(property: 'rating_breakdown', type: 'object', nullable: true),
        new OA\Property(property: 'rating_delta', type: 'integer', nullable: true),
        new OA\Property(property: 'installs_range', type: 'string', nullable: true, example: '1,000,000+'),
        new OA\Property(property: 'file_size_bytes', type: 'integer', nullable: true, example: 73822208),
    ],
)]
#[Fillable([
    'app_id', 'version_id', 'date', 'rating', 'rating_count',
    'rating_breakdown', 'rating_delta', 'installs_range', 'file_size_bytes',
])]
class AppMetric extends Model
{
    protected $table = 'app_metrics';

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
        ];
    }
}
