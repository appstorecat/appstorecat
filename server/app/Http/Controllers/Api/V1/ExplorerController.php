<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseController;
use App\Models\App;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class ExplorerController extends BaseController
{
    #[OA\Get(
        path: '/explorer/screenshots',
        summary: 'Browse screenshots across all apps',
        tags: ['Explorer'],
        operationId: 'exploreScreenshots',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'platform', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['ios', 'android'])),
            new OA\Parameter(name: 'category_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 12)),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 1)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Paginated app screenshot listings'),
        ],
    )]
    public function screenshots(Request $request): JsonResponse
    {
        $request->validate([
            'platform' => 'sometimes|in:ios,android',
            'category_id' => 'sometimes|integer|exists:store_categories,id',
            'search' => 'sometimes|string|max:100',
            'per_page' => 'sometimes|integer|min:1|max:50',
        ]);

        $query = App::query()
            ->whereHas('storeListings', function ($q) {
                $q->whereNotNull('screenshots')
                    ->where('screenshots', '!=', '[]');
            })
            ->with(['publisher', 'category', 'storeListings' => function ($q) {
                $q->orderByDesc('version_id')->limit(1);
            }]);

        if ($request->filled('platform')) {
            $query->platform($request->input('platform'));
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->integer('category_id'));
        }

        if ($request->filled('search')) {
            $query->whereHas('storeListings', fn ($q) => $q->where('title', 'like', '%'.$request->input('search').'%'));
        }

        $query->orderByDesc('last_synced_at');

        $paginated = $query->paginate($request->integer('per_page', 5));

        $data = collect($paginated->items())->map(function (App $app) {
            $listing = $app->storeListings->first();

            return [
                'app_id' => $app->id,
                'external_id' => $app->external_id,
                'platform' => $app->platform->slug(),
                'name' => $app->displayName(),
                'icon_url' => $app->displayIcon(),
                'publisher_name' => $app->publisher?->name,
                'category_name' => $app->category?->name,
                'screenshots' => $listing?->screenshotUrls() ?? [],
            ];
        });

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
        ]);
    }

    #[OA\Get(
        path: '/explorer/icons',
        summary: 'Browse app icons across all apps',
        tags: ['Explorer'],
        operationId: 'exploreIcons',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'platform', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['ios', 'android'])),
            new OA\Parameter(name: 'category_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 48)),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 1)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Paginated app icons'),
        ],
    )]
    public function icons(Request $request): JsonResponse
    {
        $request->validate([
            'platform' => 'sometimes|in:ios,android',
            'category_id' => 'sometimes|integer|exists:store_categories,id',
            'search' => 'sometimes|string|max:100',
            'per_page' => 'sometimes|integer|min:1|max:200',
        ]);

        $query = App::query()
            ->whereNotNull('display_icon')
            ->with(['publisher', 'category']);

        if ($request->filled('platform')) {
            $query->platform($request->input('platform'));
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->integer('category_id'));
        }

        if ($request->filled('search')) {
            $query->where('display_name', 'like', '%'.$request->input('search').'%');
        }

        $query->orderByDesc('discovered_at');

        $paginated = $query->paginate($request->integer('per_page', 48));

        $data = collect($paginated->items())->map(function (App $app) {
            return [
                'app_id' => $app->id,
                'external_id' => $app->external_id,
                'platform' => $app->platform->slug(),
                'name' => $app->displayName(),
                'icon_url' => $app->displayIcon(),
                'publisher_name' => $app->publisher?->name,
                'category_name' => $app->category?->name,
            ];
        });

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
        ]);
    }
}
