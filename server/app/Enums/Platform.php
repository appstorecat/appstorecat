<?php

namespace App\Enums;

use JsonSerializable;

enum Platform: int implements JsonSerializable
{
    case Ios = 1;
    case Android = 2;

    public function slug(): string
    {
        return match ($this) {
            self::Ios => 'ios',
            self::Android => 'android',
        };
    }

    public function jsonSerialize(): string
    {
        return $this->slug();
    }

    public function label(): string
    {
        return match ($this) {
            self::Ios => 'iOS',
            self::Android => 'Android',
        };
    }

    public static function fromSlug(string $slug): self
    {
        return match ($slug) {
            'ios' => self::Ios,
            'android' => self::Android,
        };
    }

    public static function tryFromSlug(?string $slug): ?self
    {
        return match ($slug) {
            'ios' => self::Ios,
            'android' => self::Android,
            default => null,
        };
    }
}
