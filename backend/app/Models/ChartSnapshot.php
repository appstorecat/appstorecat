<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ChartCollection;
use App\Enums\Platform;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'platform', 'collection', 'category_id', 'country', 'snapshot_date',
])]
class ChartSnapshot extends Model
{
    protected $table = 'trending_charts';

    /**
     * @return HasMany<ChartEntry, $this>
     */
    public function entries(): HasMany
    {
        return $this->hasMany(ChartEntry::class, 'trending_chart_id')->orderBy('rank');
    }

    /**
     * @return BelongsTo<StoreCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(StoreCategory::class, 'category_id');
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeForChart(Builder $query, string $platform, string $collection, string $country, ?int $categoryId = null): Builder
    {
        return $query
            ->where('platform', $platform)
            ->where('collection', $collection)
            ->where('country', $country)
            ->where('category_id', $categoryId);
    }

    protected function casts(): array
    {
        return [
            'platform' => Platform::class,
            'collection' => ChartCollection::class,
            'snapshot_date' => 'date',
        ];
    }
}
