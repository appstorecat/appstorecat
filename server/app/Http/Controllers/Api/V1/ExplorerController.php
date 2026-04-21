<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\Explorer\ExplorerIconsRequest;
use App\Http\Requests\Api\Explorer\ExplorerScreenshotsRequest;
use App\Http\Resources\Api\Explorer\ExplorerIconResource;
use App\Http\Resources\Api\Explorer\ExplorerScreenshotResource;
use App\Models\App;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
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
            new OA\Response(
                response: 200,
                description: 'Paginated app screenshot listings',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/ExplorerScreenshotResource')),
                        new OA\Property(property: 'links', type: 'object'),
                        new OA\Property(property: 'meta', type: 'object'),
                    ],
                ),
            ),
        ],
    )]
    public function screenshots(ExplorerScreenshotsRequest $request): AnonymousResourceCollection
    {
        // Explorer feeds use the English storefront content as the canonical
        // preview. Prefer `en-US`, then any other `en*` locale, to keep the
        // grid visually consistent regardless of the user's browser locale.
        $englishListing = fn ($q) => $q
            ->where('locale', 'like', 'en%')
            ->whereNotNull('screenshots')
            ->where('screenshots', '!=', '[]')
            ->orderByRaw("CASE WHEN locale = 'en-US' THEN 0 ELSE 1 END")
            ->orderByDesc('version_id')
            ->limit(1);

        $query = App::query()
            ->whereHas('storeListings', $englishListing)
            ->with(['publisher', 'category', 'storeListings' => $englishListing]);

        if ($request->filled('platform')) {
            $query->platform($request->validated('platform'));
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', (int) $request->validated('category_id'));
        }

        if ($request->filled('search')) {
            $query->whereHas('storeListings', fn ($q) => $q
                ->where('locale', 'like', 'en%')
                ->where('title', 'like', '%'.$request->validated('search').'%'));
        }

        $query->orderByDesc('last_synced_at');

        $paginated = $query->paginate($request->integer('per_page', 5));

        return ExplorerScreenshotResource::collection($paginated);
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
            new OA\Response(
                response: 200,
                description: 'Paginated app icons',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/ExplorerIconResource')),
                        new OA\Property(property: 'links', type: 'object'),
                        new OA\Property(property: 'meta', type: 'object'),
                    ],
                ),
            ),
        ],
    )]
    public function icons(ExplorerIconsRequest $request): AnonymousResourceCollection
    {
        // Icons gallery mirrors the English storefront. Only include apps
        // with a captured en* listing so the grid stays visually consistent.
        $englishListing = fn ($q) => $q
            ->where('locale', 'like', 'en%')
            ->whereNotNull('icon_url')
            ->orderByRaw("CASE WHEN locale = 'en-US' THEN 0 ELSE 1 END")
            ->orderByDesc('version_id')
            ->limit(1);

        $query = App::query()
            ->whereHas('storeListings', $englishListing)
            ->with(['publisher', 'category', 'storeListings' => $englishListing]);

        if ($request->filled('platform')) {
            $query->platform($request->validated('platform'));
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', (int) $request->validated('category_id'));
        }

        if ($request->filled('search')) {
            $query->where('display_name', 'like', '%'.$request->validated('search').'%');
        }

        $query->orderByDesc('discovered_at');

        $paginated = $query->paginate($request->integer('per_page', 48));

        return ExplorerIconResource::collection($paginated);
    }
}
