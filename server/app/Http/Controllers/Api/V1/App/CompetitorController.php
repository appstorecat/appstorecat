<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\App;

use App\Enums\Platform;
use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\App\CompetitorAllRequest;
use App\Http\Requests\Api\App\StoreCompetitorRequest;
use App\Http\Resources\Api\App\CompetitorGroupResource;
use App\Http\Resources\Api\App\CompetitorResource;
use App\Models\App;
use App\Models\AppCompetitor;
use App\Services\AppRegistrar;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use OpenApi\Attributes as OA;

class CompetitorController extends BaseController
{
    public function __construct(
        private readonly AppRegistrar $registrar,
    ) {
    }

    #[OA\Get(
        path: '/apps/{platform}/{externalId}/competitors',
        summary: 'List competitors for an app',
        tags: ['Apps'],
        operationId: 'listCompetitors',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'platform', in: 'path', required: true, schema: new OA\Schema(type: 'string', enum: ['ios', 'android'])),
            new OA\Parameter(name: 'externalId', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'List of competitors', content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/CompetitorResource'))),
            new OA\Response(response: 404, description: 'App not found'),
        ],
    )]
    public function index(Request $request, string $platform, string $externalId): AnonymousResourceCollection
    {
        $app = $this->resolveApp($platform, $externalId);
        abort_unless($app->isTrackedBy($request->user()), 404);

        $competitors = AppCompetitor::where('user_id', $request->user()->id)
            ->where('app_id', $app->id)
            ->with('competitorApp.publisher', 'competitorApp.category')
            ->get();

        return CompetitorResource::collection($competitors);
    }

    #[OA\Get(
        path: '/competitors',
        summary: 'List competitors grouped by parent app across all user apps',
        tags: ['Apps'],
        operationId: 'listAllCompetitors',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'platform', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['ios', 'android'])),
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string', maxLength: 100)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Competitors grouped by parent app',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(ref: '#/components/schemas/CompetitorGroupResource'),
                ),
            ),
        ],
    )]
    public function all(CompetitorAllRequest $request): AnonymousResourceCollection
    {
        $userId = $request->user()->id;
        $platform = $request->validated('platform');
        $search = trim((string) ($request->validated('search') ?? ''));
        $hasSearch = $search !== '';
        $like = '%'.$search.'%';

        $trackedApps = $request->user()->apps()
            ->with(['publisher', 'category'])
            ->when($platform, fn ($query, $p) => $query->platform($p))
            ->get();

        $competitorsQuery = AppCompetitor::where('user_id', $userId)
            ->whereIn('app_id', $trackedApps->pluck('id'))
            ->with('competitorApp.publisher', 'competitorApp.category');

        if ($hasSearch) {
            $competitorsQuery->where(function (Builder $query) use ($like) {
                $query
                    ->whereHas('app', fn (Builder $q) => $q->where('display_name', 'like', $like))
                    ->orWhereHas('competitorApp', fn (Builder $q) => $q->where('display_name', 'like', $like));
            });
        }

        $competitors = $competitorsQuery->get();

        $parentMatchIds = $hasSearch
            ? $request->user()->apps()
                ->whereIn('apps.id', $trackedApps->pluck('id'))
                ->where('apps.display_name', 'like', $like)
                ->pluck('apps.id')
                ->all()
            : [];

        $competitorsByParent = $competitors
            ->groupBy('app_id')
            ->map(function ($group, $parentId) use ($hasSearch, $parentMatchIds, $search) {
                if (! $hasSearch || in_array($parentId, $parentMatchIds, true)) {
                    return $group;
                }

                // Parent did not match — keep only competitors whose own display_name matches.
                // Mirrors the SQL LIKE (case-insensitive substring) applied in the eager query above.
                return $group->filter(
                    fn (AppCompetitor $c) => $c->competitorApp
                        && stripos((string) $c->competitorApp->display_name, $search) !== false,
                );
            })
            ->filter(fn ($group) => $group->isNotEmpty());

        $groups = $trackedApps
            ->filter(fn (App $app) => $competitorsByParent->has($app->id))
            ->map(fn (App $app) => [
                'parent' => $app,
                'competitors' => $competitorsByParent->get($app->id),
            ])
            ->values();

        return CompetitorGroupResource::collection($groups);
    }

    #[OA\Post(
        path: '/apps/{platform}/{externalId}/competitors',
        summary: 'Add a competitor to an app',
        tags: ['Apps'],
        operationId: 'storeCompetitor',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'platform', in: 'path', required: true, schema: new OA\Schema(type: 'string', enum: ['ios', 'android'])),
            new OA\Parameter(name: 'externalId', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/StoreCompetitorRequest'),
        ),
        responses: [
            new OA\Response(response: 201, description: 'Competitor added', content: new OA\JsonContent(ref: '#/components/schemas/CompetitorResource')),
            new OA\Response(response: 404, description: 'App not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ],
    )]
    public function store(StoreCompetitorRequest $request, string $platform, string $externalId): CompetitorResource
    {
        $app = $this->resolveApp($platform, $externalId);
        abort_unless($app->isTrackedBy($request->user()), 404);

        // Resolve the competitor app row. Two flows:
        //   - Preferred: caller supplies `competitor_external_id` (+ optional
        //     `competitor_platform`) and we ensure a row exists in `apps`
        //     without touching the caller's `user_apps` watchlist.
        //   - Legacy: caller supplies `competitor_app_id`, an internal id of
        //     an already-registered app.
        $competitorAppId = $request->input('competitor_app_id');
        if ($request->filled('competitor_external_id')) {
            $competitorPlatform = $request->input('competitor_platform')
                ? Platform::fromSlug($request->input('competitor_platform'))
                : $app->platform;

            $competitorApp = $this->registrar->ensureExists(
                $request->input('competitor_external_id'),
                $competitorPlatform,
            );

            $competitorAppId = $competitorApp->id;
        }

        $competitor = AppCompetitor::create([
            'user_id' => $request->user()->id,
            'app_id' => $app->id,
            'competitor_app_id' => $competitorAppId,
            'relationship' => $request->relationship ?? 'direct',
        ]);

        $competitor->load('competitorApp.publisher', 'competitorApp.category');

        return new CompetitorResource($competitor);
    }

    #[OA\Delete(
        path: '/apps/{platform}/{externalId}/competitors/{competitor}',
        summary: 'Remove a competitor from an app',
        tags: ['Apps'],
        operationId: 'deleteCompetitor',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'platform', in: 'path', required: true, schema: new OA\Schema(type: 'string', enum: ['ios', 'android'])),
            new OA\Parameter(name: 'externalId', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'competitor', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Competitor removed'),
            new OA\Response(response: 404, description: 'Not found'),
        ],
    )]
    public function destroy(Request $request, string $platform, string $externalId, AppCompetitor $competitor): Response
    {
        $app = $this->resolveApp($platform, $externalId);
        abort_unless($app->isTrackedBy($request->user()), 404);
        abort_unless($competitor->user_id === $request->user()->id, 404);
        abort_unless($competitor->app_id === $app->id, 404);

        $competitor->delete();

        return response()->noContent();
    }
}
