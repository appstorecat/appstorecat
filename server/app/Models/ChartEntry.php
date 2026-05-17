<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ChartEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'trending_chart_id', 'rank', 'app_id',
    'price', 'currency',
])]
class ChartEntry extends Model
{
    /** @use HasFactory<ChartEntryFactory> */
    use HasFactory;

    protected $table = 'trending_chart_entries';

    public $timestamps = false;

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
