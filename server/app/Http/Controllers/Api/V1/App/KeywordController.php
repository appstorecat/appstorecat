<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\App;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\App\KeywordCompareRequest;
use App\Http\Requests\Api\App\KeywordIndexRequest;
use App\Http\Resources\Api\App\KeywordCompareResource;
use App\Http\Resources\Api\App\KeywordDensityResource;
use App\Models\App;
use App\Models\StoreListing;
use App\Services\KeywordAnalyzer;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use OpenApi\Attributes as OA;

class KeywordController extends BaseController
{
    public function __construct(private readonly KeywordAnalyzer $analyzer) {}

    #[OA\Get(
        path: '/apps/{platform}/{externalId}/keywords',
        summary: 'Get keyword density for an app',
        tags: ['Apps'],
        operationId: 'appKeywords',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'platform', in: 'path', required: true, schema: new OA\Schema(type: 'string', enum: ['ios', 'android'])),
            new OA\Parameter(name: 'externalId', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'locale', in: 'query', required: false, schema: new OA\Schema(type: 'string', example: 'en-US')),
            new OA\Parameter(name: 'ngram', in: 'query', required: false, schema: new OA\Schema(type: 'integer', enum: [1, 2, 3], example: 1)),
            new OA\Parameter(name: 'version_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string', maxLength: 100)),
            new OA\Parameter(name: 'sort', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['keyword', 'count', 'density'], default: 'density')),
            new OA\Parameter(name: 'order', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['asc', 'desc'], default: 'desc')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 500, default: 100)),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 1, default: 1)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Paginated keyword density list',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/KeywordDensityResource')),
                        new OA\Property(property: 'links', type: 'object'),
                        new OA\Property(property: 'meta', type: 'object'),
                    ],
                ),
            ),
            new OA\Response(response: 404, description: 'App not found'),
        ],
    )]
    public function index(KeywordIndexRequest $request, string $platform, string $externalId): AnonymousResourceCollection
    {
        $app = $this->resolveApp($platform, $externalId);

        $locale = $request->validated('locale') ?? 'en-US';
        $ngram = (int) ($request->validated('ngram') ?? 1);
        $versionIdRaw = $request->validated('version_id') ?? $app->versions()->value('id');
        $versionId = $versionIdRaw !== null ? (int) $versionIdRaw : null;

        $search = $request->validated('search');
        $sort = $request->validated('sort') ?? 'density';
        $order = $request->validated('order') ?? 'desc';
        $perPage = (int) ($request->validated('per_page') ?? 100);
        $page = (int) ($request->validated('page') ?? Paginator::resolveCurrentPage());

        $listing = $this->findListing($app->id, $versionId, $locale);

        if (! $listing) {
            $empty = new LengthAwarePaginator([], 0, $perPage, $page, [
                'path' => Paginator::resolveCurrentPath(),
                'pageName' => 'page',
            ]);

            return KeywordDensityResource::collection($empty);
        }

        // Keyword analysis is computed in PHP over the full listing corpus.
        // We filter, sort, and paginate the resulting collection in-memory —
        // server-side shape matches an Eloquent paginator so the client can
        // read `data`, `links`, `meta` uniformly.
        $rows = collect($this->analyzer->analyzeListing($listing))
            ->filter(fn (array $row) => $row['ngram_size'] === $ngram)
            ->map(fn (array $row) => array_merge($row, ['locale' => $listing->locale]))
            ->values();

        if ($search !== null && $search !== '') {
            $needle = mb_strtolower($search);
            $rows = $rows->filter(fn (array $row) => str_contains(mb_strtolower((string) $row['keyword']), $needle))
                ->values();
        }

        $sortedRows = $order === 'asc'
            ? $rows->sortBy($sort, SORT_REGULAR, false)->values()
            : $rows->sortByDesc($sort)->values();

        $total = $sortedRows->count();
        $items = $sortedRows->forPage($page, $perPage)->values()->all();

        $paginator = new LengthAwarePaginator($items, $total, $perPage, $page, [
            'path' => Paginator::resolveCurrentPath(),
            'pageName' => 'page',
        ]);
        $paginator->appends($request->query());

        return KeywordDensityResource::collection($paginator);
    }

    #[OA\Get(
        path: '/apps/{platform}/{externalId}/keywords/compare',
        summary: 'Compare keyword density with other apps',
        tags: ['Apps'],
        operationId: 'compareKeywords',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'platform', in: 'path', required: true, schema: new OA\Schema(type: 'string', enum: ['ios', 'android'])),
            new OA\Parameter(name: 'externalId', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'app_ids', in: 'query', required: true, description: 'App IDs to compare (max 5)', schema: new OA\Schema(type: 'array', items: new OA\Items(type: 'integer'))),
            new OA\Parameter(name: 'version_ids', in: 'query', required: false, description: 'Version ID per app (keyed by app ID)', schema: new OA\Schema(type: 'object', additionalProperties: new OA\AdditionalProperties(type: 'integer'))),
            new OA\Parameter(name: 'locale', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'ngram', in: 'query', required: false, schema: new OA\Schema(type: 'integer', enum: [1, 2, 3])),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Keyword comparison data',
                content: new OA\JsonContent(ref: '#/components/schemas/KeywordCompareResource'),
            ),
            new OA\Response(response: 404, description: 'App not found'),
        ],
    )]
    public function compare(KeywordCompareRequest $request, string $platform, string $externalId): KeywordCompareResource
    {
        $this->resolveApp($platform, $externalId);

        $compareAppIds = collect($request->validated('app_ids'))
            ->map(fn ($id) => (int) $id)
            ->values();

        $locale = $request->validated('locale') ?? 'en-US';
        $ngram = (int) ($request->validated('ngram') ?? 1);
        $versionIds = $request->validated('version_ids') ?? [];

        $compareApps = App::whereIn('id', $compareAppIds)
            ->with('versions')
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'name' => $a->name,
                'icon_url' => $a->storeListings()->orderByDesc('version_id')->value('icon_url'),
                'versions' => $a->versions->map(fn ($v) => ['id' => $v->id, 'version' => $v->version]),
            ]);

        $keywordsGrouped = [];

        foreach ($compareAppIds as $appId) {
            $compareApp = App::find($appId);
            if (! $compareApp) {
                continue;
            }

            $versionIdRaw = $versionIds[$appId] ?? $compareApp->versions()->value('id');
            if (! $versionIdRaw) {
                continue;
            }
            $versionId = (int) $versionIdRaw;

            $listing = $this->findListing($appId, $versionId, $locale);
            if (! $listing) {
                continue;
            }

            $keywordsGrouped[(string) $appId] = collect($this->analyzer->analyzeListing($listing))
                ->filter(fn (array $row) => $row['ngram_size'] === $ngram)
                ->keyBy('keyword')
                ->map(fn (array $row) => [
                    'count' => $row['count'],
                    'density' => (float) $row['density'],
                ]);
        }

        return new KeywordCompareResource([
            'apps' => $compareApps,
            'keywords' => (object) $keywordsGrouped,
        ]);
    }

    private function findListing(int $appId, ?int $versionId, string $locale): ?StoreListing
    {
        $query = StoreListing::where('app_id', $appId)->where('locale', $locale);
        if ($versionId) {
            $query->where('version_id', $versionId);
        }

        return $query->orderByDesc('version_id')->first();
    }
}
