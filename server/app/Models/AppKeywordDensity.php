<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'AppKeywordDensity',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'language', type: 'string', example: 'us'),
        new OA\Property(property: 'ngram_size', type: 'integer', example: 2),
        new OA\Property(property: 'keyword', type: 'string', example: 'photo editor'),
        new OA\Property(property: 'count', type: 'integer', example: 5),
        new OA\Property(property: 'density', type: 'number', format: 'float', example: 2.35),
    ],
)]
#[Fillable([
    'app_id', 'version_id', 'language', 'ngram_size',
    'keyword', 'count', 'density',
])]
class AppKeywordDensity extends Model
{
    protected $table = 'app_keyword_densities';

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
}
