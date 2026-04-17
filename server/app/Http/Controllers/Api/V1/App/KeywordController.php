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
            new OA\Parameter(name: 'language', in: 'query', required: false, schema: new OA\Schema(type: 'string', example: 'en-US')),
            new OA\Parameter(name: 'ngram', in: 'query', required: false, schema: new OA\Schema(type: 'integer', enum: [1, 2, 3], example: 1)),
            new OA\Parameter(name: 'version_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Keyword density list',
                content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/KeywordDensityResource')),
            ),
            new OA\Response(response: 404, description: 'App not found'),
        ],
    )]
    public function index(KeywordIndexRequest $request, string $platform, string $externalId): AnonymousResourceCollection
    {
        $app = $this->resolveApp($platform, $externalId);

        $language = $request->validated('language') ?? 'en-US';
        $ngram = (int) ($request->validated('ngram') ?? 1);
        $versionIdRaw = $request->validated('version_id') ?? $app->versions()->value('id');
        $versionId = $versionIdRaw !== null ? (int) $versionIdRaw : null;

        $listing = $this->findListing($app->id, $versionId, $language);

        if (! $listing) {
            return KeywordDensityResource::collection([]);
        }

        $rows = collect($this->analyzer->analyzeListing($listing))
            ->filter(fn (array $row) => $row['ngram_size'] === $ngram)
            ->sortByDesc('count')
            ->values()
            ->map(fn (array $row) => array_merge($row, ['language' => $listing->language]))
            ->all();

        return KeywordDensityResource::collection($rows);
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
            new OA\Parameter(name: 'language', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
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

        $language = $request->validated('language') ?? 'en-US';
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

            $listing = $this->findListing($appId, $versionId, $language);
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

    private function findListing(int $appId, ?int $versionId, string $language): ?StoreListing
    {
        $query = StoreListing::where('app_id', $appId)->where('language', $language);
        if ($versionId) {
            $query->where('version_id', $versionId);
        }

        return $query->orderByDesc('version_id')->first();
    }
}
