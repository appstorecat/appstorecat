<?php

declare(strict_types=1);

namespace App\Http\Resources\Api;

use OpenApi\Attributes as OA;

/**
 * Schema-only class documenting the paginator envelope that wraps
 * `ChangeResource::collection($paginator)`. Swagger refs this shape so Orval
 * generates the correct paginated response type for both change-monitor
 * endpoints.
 *
 * This file holds no runtime behaviour — `ChangeResource` itself performs
 * the per-row transformation; the controller adds `meta_ext` via
 * `additional(...)`.
 */
#[OA\Schema(
    schema: 'PaginatedChangeResponse',
    required: ['data', 'meta'],
    properties: [
        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/ChangeResource')),
        new OA\Property(property: 'links', type: 'object', properties: [
            new OA\Property(property: 'first', type: 'string', nullable: true),
            new OA\Property(property: 'last', type: 'string', nullable: true),
            new OA\Property(property: 'prev', type: 'string', nullable: true),
            new OA\Property(property: 'next', type: 'string', nullable: true),
        ]),
        new OA\Property(property: 'meta', type: 'object', properties: [
            new OA\Property(property: 'current_page', type: 'integer'),
            new OA\Property(property: 'last_page', type: 'integer'),
            new OA\Property(property: 'per_page', type: 'integer'),
            new OA\Property(property: 'total', type: 'integer'),
        ]),
        new OA\Property(property: 'meta_ext', type: 'object', properties: [
            new OA\Property(property: 'has_scope_apps', type: 'boolean'),
        ]),
    ],
)]
final class PaginatedChangeResponse {}
