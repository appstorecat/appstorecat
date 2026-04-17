<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Enums\Platform;
use Illuminate\Database\Eloquent\Builder;

/**
 * Normalizes the `platform` column writes so that string slugs
 * (e.g. "ios", "android") transparently become their int-backed
 * Platform enum values when assigned via create/update.
 */
trait HasPlatform
{
    /**
     * Scope helper: `Model::platform('ios')` normalizes string/enum/int input.
     *
     * @param  Builder<static>  $query
     */
    public function scopePlatform(Builder $query, Platform|string|int $value): Builder
    {
        return $query->where(
            $query->getModel()->getTable().'.platform',
            self::normalizePlatform($value),
        );
    }

    public static function normalizePlatform(Platform|string|int $value): int
    {
        return match (true) {
            $value instanceof Platform => $value->value,
            is_int($value) => $value,
            ctype_digit((string) $value) => (int) $value,
            default => Platform::fromSlug($value)->value,
        };
    }
}
