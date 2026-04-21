<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\App;

use App\Connectors\GooglePlayConnector;
use App\Connectors\ITunesLookupConnector;
use App\Enums\DiscoverSource;
use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\App\AppSearchRequest;
use App\Http\Resources\Api\App\AppSearchResultResource;
use App\Models\App;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Attributes as OA;

class AppSearchController extends BaseController
{
    #[OA\Get(
        path: '/apps/search',
        summary: 'Search apps in stores',
        tags: ['Apps'],
        operationId: 'searchApps',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'term', in: 'query', required: true, schema: new OA\Schema(type: 'string', minLength: 2, maxLength: 100)),
            new OA\Parameter(name: 'platform', in: 'query', required: true, schema: new OA\Schema(type: 'string', enum: ['ios', 'android'])),
            new OA\Parameter(name: 'country_code', in: 'query', required: false, schema: new OA\Schema(type: 'string', minLength: 2, maxLength: 2, default: 'us')),
            new OA\Parameter(
                name: 'exclude_external_ids[]',
                in: 'query',
                required: false,
                explode: true,
                style: 'form',
                schema: new OA\Schema(type: 'array', items: new OA\Items(type: 'string', maxLength: 255)),
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Search results',
                content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/AppSearchResultResource')),
            ),
        ],
    )]
    public function __invoke(AppSearchRequest $request, ITunesLookupConnector $ios, GooglePlayConnector $android): AnonymousResourceCollection
    {
        $term = $request->input('term');
        $platform = $request->input('platform');
        $countryCode = $request->input('country_code', 'us');
        $excludedExternalIds = collect($request->validated('exclude_external_ids') ?? [])
            ->filter()
            ->map(fn ($id) => (string) $id);

        $connector = $platform === 'ios' ? $ios : $android;
        $results = $connector->fetchSearch($term, 10, $countryCode);

        $apps = collect($results)->map(function ($result) use ($platform, $countryCode) {
            return App::discover($platform, $result['app_id'] ?? '', [
                'name' => $result['name'] ?? '',
                'developer' => $result['developer'] ?? null,
                'developer_id' => $result['developer_id'] ?? null,
                'icon_url' => $result['icon_url'] ?? null,
                'genre' => $result['genre'] ?? null,
                'genre_id' => $result['genre_id'] ?? null,
            ], DiscoverSource::Search, $countryCode);
        })->filter();

        if ($excludedExternalIds->isNotEmpty()) {
            $apps = $apps->reject(fn (App $app) => $excludedExternalIds->contains($app->external_id));
        }

        return AppSearchResultResource::collection($apps->values());
    }
}
