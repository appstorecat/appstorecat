<?php

declare(strict_types=1);

namespace App\Casts;

use App\Enums\Platform;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Accepts string slug ("ios"/"android") or Platform enum as input,
 * stores as int in DB, hydrates back to Platform enum.
 *
 * @implements CastsAttributes<Platform|null, Platform|string|int|null>
 */
class PlatformCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Platform
    {
        if ($value === null) {
            return null;
        }

        return Platform::from((int) $value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?int
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof Platform) {
            return $value->value;
        }

        if (is_int($value) || ctype_digit((string) $value)) {
            return (int) $value;
        }

        return Platform::fromSlug((string) $value)->value;
    }
}
