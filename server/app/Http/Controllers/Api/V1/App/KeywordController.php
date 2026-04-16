<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\App;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\App\KeywordCompareRequest;
use App\Http\Requests\Api\App\KeywordIndexRequest;
use App\Http\Resources\Api\App\KeywordCompareResource;
use App\Http\Resources\Api\App\KeywordDensityResource;
use App\Models\App;
use App\Models\AppKeywordDensity;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Attributes as OA;

class KeywordController extends BaseController
{
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
            new OA\Parameter(name: 'ngram', in: 'query', required: false, schema: new OA\Schema(type: 'integer', enum: [1, 2, 3, 4], example: 1)),
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

        $versionId = $request->validated('version_id')
            ?? $app->versions()->value('id');

        if (! $versionId) {
            return KeywordDensityResource::collection([]);
        }

        $language = $request->validated('language') ?? 'en-US';
        $ngram = $request->validated('ngram') ?? 1;

        $keywords = AppKeywordDensity::where('app_id', $app->id)
            ->where('version_id', $versionId)
            ->where('language', $language)
            ->where('ngram_size', $ngram)
            ->orderByDesc('count')
            ->get();

        return KeywordDensityResource::collection($keywords);
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
            new OA\Parameter(name: 'ngram', in: 'query', required: false, schema: new OA\Schema(type: 'integer', enum: [1, 2, 3, 4])),
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

            $versionId = $versionIds[$appId] ?? $compareApp->versions()->value('id');
            if (! $versionId) {
                continue;
            }

            $keywords = AppKeywordDensity::where('app_id', $appId)
                ->where('version_id', $versionId)
                ->where('language', $language)
                ->where('ngram_size', $ngram)
                ->get()
                ->keyBy('keyword')
                ->map(fn ($kw) => [
                    'count' => $kw->count,
                    'density' => (float) $kw->density,
                ]);

            $keywordsGrouped[(string) $appId] = $keywords;
        }

        return new KeywordCompareResource([
            'apps' => $compareApps,
            'keywords' => (object) $keywordsGrouped,
        ]);
    }
}
