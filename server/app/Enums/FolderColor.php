<?php

declare(strict_types=1);

namespace App\Enums;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'FolderColorEnum',
    type: 'string',
    enum: ['slate', 'red', 'orange', 'amber', 'yellow', 'green', 'teal', 'blue', 'purple', 'pink'],
    description: 'Folder color slug; resolved to Tailwind classes on the client.',
)]
enum FolderColor: string
{
    case Slate = 'slate';
    case Red = 'red';
    case Orange = 'orange';
    case Amber = 'amber';
    case Yellow = 'yellow';
    case Green = 'green';
    case Teal = 'teal';
    case Blue = 'blue';
    case Purple = 'purple';
    case Pink = 'pink';
}
