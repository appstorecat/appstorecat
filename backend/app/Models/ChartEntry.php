<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'trending_chart_id', 'rank', 'app_id',
    'price', 'currency',
])]
class ChartEntry extends Model
{
    protected $table = 'trending_chart_entries';

    /**
     * @return BelongsTo<ChartSnapshot, $this>
     */
    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(ChartSnapshot::class, 'trending_chart_id');
    }

    /**
     * @return BelongsTo<App, $this>
     */
    public function app(): BelongsTo
    {
        return $this->belongsTo(App::class);
    }
}
