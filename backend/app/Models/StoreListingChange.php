<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'StoreListingChange',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'version_id', type: 'integer', nullable: true),
        new OA\Property(property: 'language', type: 'string', example: 'us'),
        new OA\Property(property: 'field_changed', type: 'string', example: 'description'),
        new OA\Property(property: 'old_value', type: 'string', nullable: true),
        new OA\Property(property: 'new_value', type: 'string', nullable: true),
        new OA\Property(property: 'detected_at', type: 'string', format: 'date-time'),
    ],
)]
#[Fillable([
    'app_id', 'version_id', 'language', 'field_changed',
    'old_value', 'new_value', 'detected_at',
])]
class StoreListingChange extends Model
{
    protected $table = 'app_store_listing_changes';

    /**
     * @return BelongsTo<App, $this>
     */
    public function app(): BelongsTo
    {
        return $this->belongsTo(App::class);
    }

    protected function casts(): array
    {
        return [
            'detected_at' => 'datetime',
        ];
    }
}
