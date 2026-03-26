<?php

declare(strict_types=1);

namespace App\Enums;

enum ChartCollection: string
{
    case TopFree = 'top_free';
    case TopPaid = 'top_paid';
    case TopGrossing = 'top_grossing';
}
