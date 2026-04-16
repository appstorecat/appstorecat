<?php

namespace App\Enums;

use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'CompetitorRelationship', type: 'string', enum: ['direct', 'indirect', 'aspiration'], example: 'direct')]
enum CompetitorRelationship: string
{
    case Direct = 'direct';
    case Indirect = 'indirect';
    case Aspiration = 'aspiration';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
