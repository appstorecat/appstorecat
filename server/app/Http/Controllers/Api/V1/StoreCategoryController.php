<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\StoreCategory\StoreCategoryIndexRequest;
use App\Http\Resources\Api\StoreCategory\StoreCategoryResource;
use App\Models\StoreCategory;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Attributes as OA;

class StoreCategoryController extends BaseController
{
    #[OA\Get(
        path: '/store-categories',
        summary: 'List store categories',
        tags: ['Store Categories'],
        operationId: 'listStoreCategories',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'platform', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['ios', 'android'])),
            new OA\Parameter(name: 'type', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['app', 'game', 'magazine'])),
        ],
        responses: [
            new OA\Response(response: 200, description: 'List of categories', content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/StoreCategoryResource'))),
        ],
    )]
    public function index(StoreCategoryIndexRequest $request): AnonymousResourceCollection
    {
        $query = StoreCategory::query()->orderBy('name');

        if ($request->has('platform')) {
            $query->platform($request->validated('platform'));
        }

        if ($request->has('type')) {
            $query->where('type', $request->validated('type'));
        }

        return StoreCategoryResource::collection($query->get());
    }
}
