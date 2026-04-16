<?php

namespace App\Models;

use Database\Factories\ReviewFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Review',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'country_code', type: 'string', example: 'US'),
        new OA\Property(property: 'external_id', type: 'string', nullable: true),
        new OA\Property(property: 'author', type: 'string', nullable: true, example: 'John'),
        new OA\Property(property: 'title', type: 'string', nullable: true, example: 'Great app'),
        new OA\Property(property: 'body', type: 'string', nullable: true),
        new OA\Property(property: 'rating', type: 'integer', example: 5),
        new OA\Property(property: 'review_date', type: 'string', format: 'date', nullable: true),
        new OA\Property(property: 'app_version', type: 'string', nullable: true, example: '1.2.4'),
    ],
)]
#[Fillable([
    'app_id', 'country_code', 'external_id',
    'author', 'title', 'body', 'rating', 'review_date',
    'app_version',
])]
class Review extends Model
{
    /** @use HasFactory<ReviewFactory> */
    use HasFactory;

    protected $table = 'app_reviews';

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
            'review_date' => 'date',
        ];
    }
}
