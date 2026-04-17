<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Connectors\GooglePlayConnector;
use App\Connectors\ITunesLookupConnector;
use App\Enums\DiscoverSource;
use App\Enums\Platform;
use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\Publisher\PublisherImportRequest;
use App\Http\Requests\Api\Publisher\PublisherSearchRequest;
use App\Http\Resources\Api\Publisher\PublisherDetailResource;
use App\Http\Resources\Api\Publisher\PublisherResource;
use App\Http\Resources\Api\Publisher\PublisherSearchResultResource;
use App\Http\Resources\Api\Publisher\StoreAppResource;
use App\Models\App;
use App\Models\Publisher;
use App\Services\AppRegistrar;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;

class PublisherController extends BaseController
{
    #[OA\Get(
        path: '/publishers/search',
        summary: 'Search publishers across stores',
        tags: ['Publishers'],
        operationId: 'searchPublishers',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'term', in: 'query', required: true, schema: new OA\Schema(type: 'string', minLength: 2)),
            new OA\Parameter(name: 'platform', in: 'query', required: true, schema: new OA\Schema(type: 'string', enum: ['ios', 'android'])),
            new OA\Parameter(name: 'country', in: 'query', required: false, schema: new OA\Schema(type: 'string', minLength: 2, maxLength: 2, default: 'us')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Publisher search results',
                content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/PublisherSearchResultResource')),
            ),
        ],
    )]
    public function search(PublisherSearchRequest $request, ITunesLookupConnector $ios, GooglePlayConnector $android): AnonymousResourceCollection
    {
        $term = $request->validated('term');
        $platform = $request->validated('platform');
        $country = $request->validated('country') ?? 'us';

        $connector = $platform === 'ios' ? $ios : $android;

        try {
            $results = $connector->fetchSearch($term, 25, $country);
        } catch (\Throwable $e) {
            Log::warning("{$platform} publisher search failed", ['error' => $e->getMessage()]);

            return PublisherSearchResultResource::collection([]);
        }

        $grouped = $this->groupByPublisher($results, $platform);

        return PublisherSearchResultResource::collection($grouped);
    }

    #[OA\Get(
        path: '/publishers',
        summary: 'List publishers from user apps',
        tags: ['Publishers'],
        operationId: 'listPublishers',
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Publisher list',
                content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/PublisherResource')),
            ),
        ],
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $appIds = $request->user()->apps()->pluck('apps.id');

        $publishers = Publisher::whereHas('apps', fn ($q) => $q->whereIn('apps.id', $appIds))
            ->withCount(['apps' => fn ($q) => $q->whereIn('apps.id', $appIds)])
            ->orderBy('name')
            ->get();

        return PublisherResource::collection($publishers);
    }

    #[OA\Get(
        path: '/publishers/{platform}/{externalId}',
        summary: 'Get publisher details with tracked apps',
        tags: ['Publishers'],
        operationId: 'showPublisher',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'platform', in: 'path', required: true, schema: new OA\Schema(type: 'string', enum: ['ios', 'android'])),
            new OA\Parameter(name: 'externalId', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'name', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Publisher details',
                content: new OA\JsonContent(ref: '#/components/schemas/PublisherDetailResource'),
            ),
        ],
    )]
    public function show(Request $request, string $platform, string $externalId): PublisherDetailResource
    {
        $publisher = Publisher::platform($platform)->where('external_id', $externalId)->first();

        if (! $publisher) {
            $name = $request->query('name', $externalId);
            $placeholder = new Publisher(['name' => $name, 'external_id' => $externalId, 'platform' => $platform]);
            $placeholder->setRelation('trackedApps', collect());

            return PublisherDetailResource::make($placeholder);
        }

        $userAppIds = $request->user()->apps()->pluck('apps.id');
        $publisher->setRelation(
            'trackedApps',
            $publisher->apps()->whereIn('id', $userAppIds)->with(['publisher', 'category'])->get()
        );

        return PublisherDetailResource::make($publisher);
    }

    #[OA\Get(
        path: '/publishers/{platform}/{externalId}/store-apps',
        summary: 'Fetch all apps by publisher from store',
        tags: ['Publishers'],
        operationId: 'publisherStoreApps',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'platform', in: 'path', required: true, schema: new OA\Schema(type: 'string', enum: ['ios', 'android'])),
            new OA\Parameter(name: 'externalId', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Apps from store',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'apps', type: 'array', items: new OA\Items(ref: '#/components/schemas/StoreAppResource')),
                    ],
                ),
            ),
        ],
    )]
    public function storeApps(Request $request, string $platform, string $externalId, ITunesLookupConnector $ios, GooglePlayConnector $android): AnonymousResourceCollection
    {
        $platformEnum = Platform::fromSlug($platform);
        $connector = $platformEnum === Platform::Ios ? $ios : $android;
        $result = $connector->fetchDeveloperApps($externalId);

        if (! $result->success) {
            return StoreAppResource::collection([]);
        }

        $userAppIds = $request->user()->apps()->pluck('apps.external_id')->toArray();

        $publisherName = $request->query('name', $externalId);
        $publisher = Publisher::findOrCreateByName($publisherName, $platform, $externalId);

        $apps = collect($result->data['apps'] ?? [])->map(function ($a) use ($platform, $userAppIds, $publisher) {
            App::discover($platform, $a['external_id'], [
                'name' => $a['name'] ?? null,
                'developer' => $publisher?->name,
                'developer_id' => $publisher?->external_id,
                'icon_url' => $a['icon_url'] ?? null,
                'genre' => $a['category'] ?? null,
                'genre_id' => $a['category_id'] ?? null,
                'free' => $a['is_free'] ?? true,
            ], DiscoverSource::PublisherApps);

            return array_merge($a, [
                'is_tracked' => in_array($a['external_id'], $userAppIds),
                'icon_url' => $a['icon_url'] ?? null,
            ]);
        })->values();

        return StoreAppResource::collection($apps);
    }

    #[OA\Post(
        path: '/publishers/{platform}/{externalId}/import',
        summary: 'Import and track apps from publisher',
        tags: ['Publishers'],
        operationId: 'importPublisherApps',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'platform', in: 'path', required: true, schema: new OA\Schema(type: 'string', enum: ['ios', 'android'])),
            new OA\Parameter(name: 'externalId', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['external_ids'],
                properties: [
                    new OA\Property(property: 'external_ids', type: 'array', items: new OA\Items(type: 'string')),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 204, description: 'Apps imported'),
        ],
    )]
    public function import(PublisherImportRequest $request, string $platform, string $externalId, AppRegistrar $registrar): Response
    {
        $platformEnum = Platform::fromSlug($platform);

        foreach ($request->validated('external_ids') as $appExternalId) {
            $registrar->register($request->user(), $appExternalId, $platformEnum);
        }

        return response()->noContent();
    }

    private function groupByPublisher(array $results, string $platform): array
    {
        $grouped = [];

        foreach ($results as $item) {
            $devName = $item['developer'] ?? '';
            $devExtId = $item['developer_id'] ?? null;

            if (! $devName) {
                continue;
            }

            $key = $devExtId ?? $devName;
            if (! isset($grouped[$key])) {
                $grouped[$key] = [
                    'external_id' => $devExtId ?? $devName,
                    'name' => $devName,
                    'url' => null,
                    'platform' => $platform,
                    'app_count' => 0,
                    'sample_apps' => [],
                ];
            }

            $grouped[$key]['app_count']++;
            if (count($grouped[$key]['sample_apps']) < 3) {
                $grouped[$key]['sample_apps'][] = [
                    'name' => $item['name'] ?? '',
                    'icon_url' => $item['icon_url'] ?? null,
                ];
            }

            if (! empty($item['app_id'])) {
                App::discover($platform, $item['app_id'], [
                    'name' => $item['name'] ?? null,
                    'developer' => $devName,
                    'developer_id' => $devExtId,
                    'icon_url' => $item['icon_url'] ?? null,
                    'genre' => $item['genre'] ?? null,
                    'genre_id' => $item['genre_id'] ?? null,
                ], DiscoverSource::Search);
            }
        }

        return array_values($grouped);
    }
}
