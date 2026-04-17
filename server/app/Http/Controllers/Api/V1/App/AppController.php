<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\App;

use App\Enums\Platform;
use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\App\StoreAppRequest;
use App\Http\Resources\Api\App\AppDetailResource;
use App\Http\Resources\Api\App\AppResource;
use App\Http\Resources\Api\App\ListingResource;
use App\Models\AppCompetitor;
use App\Models\StoreListing;
use App\Services\AppRegistrar;
use App\Services\AppSyncer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use OpenApi\Attributes as OA;

class AppController extends BaseController
{
    public function __construct(
        private readonly AppRegistrar $registrar,
        private readonly AppSyncer $syncer,
    ) {}

    #[OA\Get(
        path: '/apps',
        summary: 'List tracked apps',
        tags: ['Apps'],
        operationId: 'listApps',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'platform', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['ios', 'android'])),
        ],
        responses: [
            new OA\Response(response: 200, description: 'List of tracked apps', content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/AppResource'))),
        ],
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $apps = $request->user()->apps()
            ->when($request->query('platform'), function ($query, $platform) {
                $query->platform($platform);
            })
            ->latest()
            ->get();

        return AppResource::collection($apps);
    }

    #[OA\Post(
        path: '/apps',
        summary: 'Register and track an app',
        tags: ['Apps'],
        operationId: 'storeApp',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/StoreAppRequest'),
        ),
        responses: [
            new OA\Response(response: 201, description: 'App registered', content: new OA\JsonContent(ref: '#/components/schemas/AppDetailResource')),
            new OA\Response(response: 422, description: 'Validation error'),
        ],
    )]
    public function store(StoreAppRequest $request): JsonResponse
    {
        $app = $this->registrar->register(
            $request->user(),
            $request->external_id,
            Platform::fromSlug($request->platform),
        );

        return AppDetailResource::make($app->fresh())
            ->response()
            ->setStatusCode(201);
    }

    #[OA\Get(
        path: '/apps/{platform}/{externalId}',
        summary: 'Get app details',
        tags: ['Apps'],
        operationId: 'showApp',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'platform', in: 'path', required: true, schema: new OA\Schema(type: 'string', enum: ['ios', 'android'])),
            new OA\Parameter(name: 'externalId', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'App details', content: new OA\JsonContent(ref: '#/components/schemas/AppDetailResource')),
        ],
    )]
    public function show(Request $request, string $platform, string $externalId): AppDetailResource
    {
        $app = $this->resolveOrCreateApp($platform, $externalId);

        if (! $app->last_synced_at) {
            $this->syncer->syncAll($app);
        }

        $app->refresh()->load(['storeListings', 'versions', 'storeListingChanges']);

        if ($app->isTrackedBy($request->user())) {
            $app->setRelation(
                'competitors',
                AppCompetitor::where('user_id', $request->user()->id)
                    ->where('app_id', $app->id)
                    ->with('competitorApp.publisher', 'competitorApp.category')
                    ->get()
            );
        } else {
            $app->setRelation('competitors', collect());
        }

        return AppDetailResource::make($app);
    }

    #[OA\Get(
        path: '/apps/{platform}/{externalId}/listing',
        summary: 'Get store listing for a specific country',
        tags: ['Apps'],
        operationId: 'appListing',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'platform', in: 'path', required: true, schema: new OA\Schema(type: 'string', enum: ['ios', 'android'])),
            new OA\Parameter(name: 'externalId', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'country', in: 'query', required: true, schema: new OA\Schema(type: 'string', example: 'tr')),
            new OA\Parameter(name: 'language', in: 'query', required: false, schema: new OA\Schema(type: 'string', example: 'tr')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Store listing', content: new OA\JsonContent(ref: '#/components/schemas/ListingResource')),
        ],
    )]
    public function listing(Request $request, string $platform, string $externalId): ListingResource
    {
        $request->validate([
            'country' => 'required|string|size:2|exists:countries,code',
            'language' => 'required|string|max:10',
        ]);

        $app = $this->resolveApp($platform, $externalId);

        if (! $app->last_synced_at) {
            $this->syncer->syncAll($app);
            $app->refresh();
        }

        $country = $request->input('country');
        $language = $request->input('language');
        $latestVersion = $app->versions()->orderByDesc('id')->first();

        $existing = StoreListing::where('app_id', $app->id)
            ->where('language', $language)
            ->where('version_id', $latestVersion?->id)
            ->first();

        if ($existing) {
            return ListingResource::make($existing);
        }

        $listing = $this->syncer->syncListingForCountry($app, $country, $language, $latestVersion);
        if (config("appstorecat.sync.{$app->platform->slug()}.reviews_enabled")) {
            $this->syncer->syncReviewsForCountry($app, $country);
        }

        return ListingResource::make($listing);
    }

    #[OA\Post(
        path: '/apps/{platform}/{externalId}/track',
        summary: 'Track an app',
        tags: ['Apps'],
        operationId: 'trackApp',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'platform', in: 'path', required: true, schema: new OA\Schema(type: 'string', enum: ['ios', 'android'])),
            new OA\Parameter(name: 'externalId', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'App tracked'),
        ],
    )]
    public function track(Request $request, string $platform, string $externalId): Response
    {
        $app = $this->resolveOrCreateApp($platform, $externalId);

        if (! $app->isTrackedBy($request->user())) {
            $request->user()->apps()->attach($app->id);
        }

        return response()->noContent();
    }

    #[OA\Delete(
        path: '/apps/{platform}/{externalId}/track',
        summary: 'Untrack an app',
        tags: ['Apps'],
        operationId: 'untrackApp',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'platform', in: 'path', required: true, schema: new OA\Schema(type: 'string', enum: ['ios', 'android'])),
            new OA\Parameter(name: 'externalId', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'App untracked'),
        ],
    )]
    public function untrack(Request $request, string $platform, string $externalId): Response
    {
        $app = $this->resolveApp($platform, $externalId);

        $request->user()->apps()->detach($app->id);

        AppCompetitor::where('user_id', $request->user()->id)
            ->where('app_id', $app->id)
            ->delete();

        return response()->noContent();
    }
}
