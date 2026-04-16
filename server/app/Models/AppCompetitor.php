<?php

namespace App\Models;

use App\Enums\CompetitorRelationship;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'AppCompetitor',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'app_id', type: 'integer'),
        new OA\Property(property: 'competitor_app_id', type: 'integer'),
        new OA\Property(property: 'relationship', ref: '#/components/schemas/CompetitorRelationship'),
    ],
)]
#[Fillable([
    'user_id', 'app_id', 'competitor_app_id', 'relationship',
])]
class AppCompetitor extends Model
{
    protected $table = 'app_competitors';

    /**
     * @return BelongsTo<App, $this>
     */
    public function app(): BelongsTo
    {
        return $this->belongsTo(App::class);
    }

    /**
     * @return BelongsTo<App, $this>
     */
    public function competitorApp(): BelongsTo
    {
        return $this->belongsTo(App::class, 'competitor_app_id');
    }

    protected function casts(): array
    {
        return [
            'relationship' => CompetitorRelationship::class,
        ];
    }
}
