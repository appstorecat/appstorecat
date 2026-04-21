<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\Change\ChangeAppsRequest;
use App\Http\Requests\Api\Change\ChangeCompetitorsRequest;
use App\Http\Resources\Api\ChangeResource;
use App\Models\AppCompetitor;
use App\Models\StoreListingChange;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Attributes as OA;

class ChangeMonitorController extends BaseController
{
    #[OA\Get(
        path: '/changes/apps',
        summary: 'List store listing changes for all tracked apps',
        tags: ['Change Monitor'],
        operationId: 'appChanges',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 25)),
            new OA\Parameter(name: 'field', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['title', 'subtitle', 'description', 'whats_new', 'screenshots', 'locale_added', 'locale_removed'])),
            new OA\Parameter(name: 'platform', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['ios', 'android'])),
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string', maxLength: 100)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Paginated app changes list',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/ChangeResource')),
                        new OA\Property(property: 'links', type: 'object'),
                        new OA\Property(property: 'meta', type: 'object'),
                    ],
                ),
            ),
        ],
    )]
    public function apps(ChangeAppsRequest $request): AnonymousResourceCollection
    {
        $competitorAppIds = AppCompetitor::where('user_id', $request->user()->id)
            ->pluck('competitor_app_id')
            ->unique();

        $trackedAppIds = $request->user()->apps()->pluck('apps.id')
            ->diff($competitorAppIds);

        $query = StoreListingChange::whereIn('app_id', $trackedAppIds)
            ->with('app')
            ->orderByDesc('detected_at')
            ->when($request->validated('field'), fn ($q, $field) => $q->where('field_changed', $field))
            ->when($request->validated('platform'), fn ($q, $platform) => $q->whereHas('app', fn ($a) => $a->platform($platform)))
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%'.$request->validated('search').'%';
                $q->whereHas('app', fn ($a) => $a->where('display_name', 'like', $term));
            });

        return ChangeResource::collection($query->paginate((int) ($request->validated('per_page') ?? 25)));
    }

    #[OA\Get(
        path: '/changes/competitors',
        summary: 'List store listing changes for all competitor apps',
        tags: ['Change Monitor'],
        operationId: 'competitorChanges',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 25)),
            new OA\Parameter(name: 'field', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['title', 'subtitle', 'description', 'whats_new', 'screenshots', 'locale_added', 'locale_removed'])),
            new OA\Parameter(name: 'platform', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['ios', 'android'])),
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string', maxLength: 100)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Paginated competitor changes list',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/ChangeResource')),
                        new OA\Property(property: 'links', type: 'object'),
                        new OA\Property(property: 'meta', type: 'object'),
                    ],
                ),
            ),
        ],
    )]
    public function competitors(ChangeCompetitorsRequest $request): AnonymousResourceCollection
    {
        $competitorAppIds = AppCompetitor::where('user_id', $request->user()->id)
            ->whereIn('app_id', $request->user()->apps()->pluck('apps.id'))
            ->pluck('competitor_app_id')
            ->unique();

        $query = StoreListingChange::whereIn('app_id', $competitorAppIds)
            ->with('app')
            ->orderByDesc('detected_at')
            ->when($request->validated('field'), fn ($q, $field) => $q->where('field_changed', $field))
            ->when($request->validated('platform'), fn ($q, $platform) => $q->whereHas('app', fn ($a) => $a->platform($platform)))
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%'.$request->validated('search').'%';
                $q->whereHas('app', fn ($a) => $a->where('display_name', 'like', $term));
            });

        return ChangeResource::collection($query->paginate((int) ($request->validated('per_page') ?? 25)));
    }
}
