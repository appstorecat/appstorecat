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
use Illuminate\Support\Collection;
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
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 50)),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'field', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['title', 'subtitle', 'description', 'whats_new', 'screenshots', 'locale_added', 'locale_removed'])),
            new OA\Parameter(name: 'platform', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['ios', 'android'])),
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string', maxLength: 100)),
            new OA\Parameter(name: 'app_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 1)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Paginated app changes list',
                content: new OA\JsonContent(ref: '#/components/schemas/PaginatedChangeResponse'),
            ),
        ],
    )]
    public function apps(ChangeAppsRequest $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $competitorAppIds = AppCompetitor::where('user_id', $user->id)
            ->pluck('competitor_app_id')
            ->unique();

        $trackedAppIds = $user->apps()->pluck('apps.id')->diff($competitorAppIds);

        return $this->buildResponse(
            $request->validated(),
            $trackedAppIds,
            $request->filled('search') ? (string) $request->validated('search') : null,
        );
    }

    #[OA\Get(
        path: '/changes/competitors',
        summary: 'List store listing changes for all competitor apps',
        tags: ['Change Monitor'],
        operationId: 'competitorChanges',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 50)),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'field', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['title', 'subtitle', 'description', 'whats_new', 'screenshots', 'locale_added', 'locale_removed'])),
            new OA\Parameter(name: 'platform', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['ios', 'android'])),
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string', maxLength: 100)),
            new OA\Parameter(name: 'app_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 1)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Paginated competitor changes list',
                content: new OA\JsonContent(ref: '#/components/schemas/PaginatedChangeResponse'),
            ),
        ],
    )]
    public function competitors(ChangeCompetitorsRequest $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $competitorAppIds = AppCompetitor::where('user_id', $user->id)
            ->whereIn('app_id', $user->apps()->pluck('apps.id'))
            ->pluck('competitor_app_id')
            ->unique();

        return $this->buildResponse(
            $request->validated(),
            $competitorAppIds,
            $request->filled('search') ? (string) $request->validated('search') : null,
        );
    }

    /**
     * Shared assembly for both tracked and competitor feeds.
     *
     * @param  array<string, mixed>  $validated
     * @param  Collection<int, int>  $scopeAppIds
     */
    private function buildResponse(array $validated, $scopeAppIds, ?string $search): AnonymousResourceCollection
    {
        $requestedAppId = $validated['app_id'] ?? null;
        $effectiveAppIds = $requestedAppId !== null
            ? $scopeAppIds->filter(fn ($id) => (int) $id === (int) $requestedAppId)->values()
            : $scopeAppIds;

        $perPage = (int) ($validated['per_page'] ?? 50);

        $paginated = StoreListingChange::whereIn('app_id', $effectiveAppIds)
            ->with('app')
            ->orderByDesc('detected_at')
            ->when($validated['field'] ?? null, fn ($q, $field) => $q->where('field_changed', $field))
            ->when($validated['platform'] ?? null, fn ($q, $platform) => $q->whereHas('app', fn ($a) => $a->platform($platform)))
            ->when($search, function ($q, $term) {
                $q->whereHas('app', fn ($a) => $a->where('display_name', 'like', '%'.$term.'%'));
            })
            ->paginate($perPage);

        return ChangeResource::collection($paginated)
            ->additional([
                'meta_ext' => [
                    'has_scope_apps' => $scopeAppIds->isNotEmpty(),
                ],
            ]);
    }
}
