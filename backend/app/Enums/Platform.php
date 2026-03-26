<?php

namespace App\Enums;

use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'Platform', type: 'string', enum: ['ios', 'android'], example: 'ios')]
enum Platform: string
{
    case Ios = 'ios';
    case Android = 'android';

    public function label(): string
    {
        return match ($this) {
            self::Ios => 'iOS',
            self::Android => 'Android',
        };
    }
}
