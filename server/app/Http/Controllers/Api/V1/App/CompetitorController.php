<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\App;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\App\StoreCompetitorRequest;
use App\Http\Resources\Api\App\CompetitorGroupResource;
use App\Http\Resources\Api\App\CompetitorResource;
use App\Models\App;
use App\Models\AppCompetitor;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use OpenApi\Attributes as OA;

class CompetitorController extends BaseController
{
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
    public function all(Request $request): AnonymousResourceCollection
    {
        $userId = $request->user()->id;
        $trackedApps = $request->user()->apps()->with(['publisher', 'category'])->get();

        $competitorsByParent = AppCompetitor::where('user_id', $userId)
            ->whereIn('app_id', $trackedApps->pluck('id'))
            ->with('competitorApp.publisher', 'competitorApp.category')
            ->get()
            ->groupBy('app_id');

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

        $competitor = AppCompetitor::create([
            'user_id' => $request->user()->id,
            'app_id' => $app->id,
            'competitor_app_id' => $request->competitor_app_id,
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
