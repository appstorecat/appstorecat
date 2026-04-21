<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\App;

use App\Enums\Platform;
use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\App\AppIndexRequest;
use App\Http\Requests\Api\App\ListingRequest;
use App\Http\Requests\Api\App\StoreAppRequest;
use App\Http\Resources\Api\App\AppDetailResource;
use App\Http\Resources\Api\App\AppResource;
use App\Http\Resources\Api\App\ListingResource;
use App\Http\Resources\Api\App\SyncStatusResource;
use App\Jobs\Sync\SyncAppJob;
use App\Models\App;
use App\Models\AppCompetitor;
use App\Models\StoreListing;
use App\Models\SyncStatus;
use App\Services\AppRegistrar;
use App\Services\AppSyncer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string', maxLength: 100)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'List of tracked apps', content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/AppResource'))),
        ],
    )]
    public function index(AppIndexRequest $request): AnonymousResourceCollection
    {
        $apps = $request->user()->apps()
            ->when($request->validated('platform'), function ($query, $platform) {
                $query->platform($platform);
            })
            ->when($request->filled('search'), function ($query) use ($request) {
                $term = '%'.$request->validated('search').'%';
                $query->where(fn ($q) => $q
                    ->where('apps.display_name', 'like', $term)
                    ->orWhere('apps.external_id', 'like', $term));
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
        $app = $this->resolveApp($platform, $externalId);

        if (! $app->last_synced_at) {
            $this->ensureSyncJob($app);
        } elseif ($this->isStale($app)) {
            $this->ensureSyncJob($app);
        }

        $app->refresh()->load(['storeListings', 'versions', 'storeListingChanges', 'syncStatus']);

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
            new OA\Parameter(name: 'country_code', in: 'query', required: true, schema: new OA\Schema(type: 'string', example: 'tr')),
            new OA\Parameter(name: 'locale', in: 'query', required: false, schema: new OA\Schema(type: 'string', example: 'tr')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Store listing', content: new OA\JsonContent(ref: '#/components/schemas/ListingResource')),
        ],
    )]
    public function listing(ListingRequest $request, string $platform, string $externalId): ListingResource
    {
        $app = $this->resolveApp($platform, $externalId);
        $locale = $request->validated('locale');
        $latestVersion = $app->versions()->orderByDesc('id')->first();

        $existing = StoreListing::where('app_id', $app->id)
            ->where('locale', $locale)
            ->where('version_id', $latestVersion?->id)
            ->first();

        if ($existing) {
            return ListingResource::make($existing);
        }

        if (! $app->last_synced_at || $this->isStale($app)) {
            $this->ensureSyncJob($app);
        }

        throw new NotFoundHttpException(
            'Listing not yet available for this locale — sync in progress.'
        );
    }

    #[OA\Post(
        path: '/apps/{platform}/{externalId}/sync',
        summary: 'Trigger a sync job for this app',
        tags: ['Apps'],
        operationId: 'syncApp',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'platform', in: 'path', required: true, schema: new OA\Schema(type: 'string', enum: ['ios', 'android'])),
            new OA\Parameter(name: 'externalId', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Sync queued or already in progress', content: new OA\JsonContent(ref: '#/components/schemas/SyncStatusResource')),
        ],
    )]
    public function sync(Request $request, string $platform, string $externalId): SyncStatusResource
    {
        $app = $this->resolveApp($platform, $externalId);
        $this->ensureSyncJob($app);

        $syncStatus = SyncStatus::firstOrCreate(
            ['app_id' => $app->id],
            ['status' => SyncStatus::STATUS_QUEUED],
        );

        return SyncStatusResource::make($syncStatus);
    }

    #[OA\Get(
        path: '/apps/{platform}/{externalId}/sync-status',
        summary: 'Get current sync status for this app',
        tags: ['Apps'],
        operationId: 'appSyncStatus',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'platform', in: 'path', required: true, schema: new OA\Schema(type: 'string', enum: ['ios', 'android'])),
            new OA\Parameter(name: 'externalId', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Sync status', content: new OA\JsonContent(ref: '#/components/schemas/SyncStatusResource')),
        ],
    )]
    public function syncStatus(Request $request, string $platform, string $externalId): SyncStatusResource
    {
        $app = $this->resolveApp($platform, $externalId);
        $syncStatus = SyncStatus::firstOrCreate(
            ['app_id' => $app->id],
            ['status' => SyncStatus::STATUS_QUEUED],
        );

        return SyncStatusResource::make($syncStatus);
    }

    /**
     * Ensure a sync job is queued. Dispatches a new SyncAppJob unless one is
     * already being processed — the ShouldBeUnique guard on the job prevents
     * duplicates in Redis.
     */
    private function ensureSyncJob(App $app): void
    {
        $status = SyncStatus::firstOrCreate(
            ['app_id' => $app->id],
            ['status' => SyncStatus::STATUS_QUEUED],
        );

        // Only skip dispatch if a worker is actively processing.
        if ($status->status === SyncStatus::STATUS_PROCESSING) {
            return;
        }

        $status->forceFill([
            'status' => SyncStatus::STATUS_QUEUED,
            'current_step' => null,
            'progress_done' => 0,
            'progress_total' => 0,
            'error_message' => null,
            'started_at' => null,
            'completed_at' => null,
        ])->save();

        SyncAppJob::dispatch($app->id)->onQueue("sync-on-demand-{$app->platform->slug()}");
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
        $app = $this->resolveApp($platform, $externalId);

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

    private function isStale(App $app): bool
    {
        $hours = (int) config("appstorecat.sync.{$app->platform->slug()}.tracked_app_refresh_hours", 24);

        return $app->last_synced_at->lt(now()->subHours($hours));
    }
}
