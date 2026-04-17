<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\App;

use App\Http\Resources\Api\BaseResource;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Expects $resource as array with keys:
 *  country, collection, category, rank, previous_rank, status, snapshot_date.
 */
#[OA\Schema(
    schema: 'AppRankingResource',
    properties: [
        new OA\Property(property: 'country', type: 'string'),
        new OA\Property(property: 'collection', type: 'string'),
        new OA\Property(property: 'category', type: 'object', nullable: true, properties: [
            new OA\Property(property: 'id', type: 'integer'),
            new OA\Property(property: 'name', type: 'string'),
        ]),
        new OA\Property(property: 'rank', type: 'integer'),
        new OA\Property(property: 'previous_rank', type: 'integer', nullable: true),
        new OA\Property(property: 'rank_change', type: 'integer', nullable: true),
        new OA\Property(property: 'status', type: 'string', enum: ['new', 'up', 'down', 'same']),
        new OA\Property(property: 'snapshot_date', type: 'string', format: 'date'),
    ],
)]
class AppRankingResource extends BaseResource
{
    protected function getResourceData(Request $request): array
    {
        /** @var array{country:string,collection:string,category:array{id:int,name:string}|null,rank:int,previous_rank:int|null,status:string,snapshot_date:string} $data */
        $data = $this->resource;

        return [
            'country' => $data['country'],
            'collection' => $data['collection'],
            'category' => $data['category'],
            'rank' => $data['rank'],
            'previous_rank' => $data['previous_rank'],
            'rank_change' => $data['previous_rank'] !== null
                ? $data['previous_rank'] - $data['rank']
                : null,
            'status' => $data['status'],
            'snapshot_date' => $data['snapshot_date'],
        ];
    }

    protected function getDefaultMessage(): string
    {
        return 'Ranking retrieved successfully';
    }
}
