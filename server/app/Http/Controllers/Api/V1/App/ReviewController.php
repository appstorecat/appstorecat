<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\App;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\App\ReviewIndexRequest;
use App\Http\Resources\Api\App\ReviewResource;
use App\Http\Resources\Api\App\ReviewSummaryResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Attributes as OA;

class ReviewController extends BaseController
{
    #[OA\Get(
        path: '/apps/{platform}/{externalId}/reviews',
        summary: 'List reviews for an app',
        tags: ['Apps'],
        operationId: 'appReviews',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'platform', in: 'path', required: true, schema: new OA\Schema(type: 'string', enum: ['ios', 'android'])),
            new OA\Parameter(name: 'externalId', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'country_code', in: 'query', required: false, schema: new OA\Schema(type: 'string', example: 'US')),
            new OA\Parameter(name: 'rating', in: 'query', required: false, schema: new OA\Schema(type: 'integer', enum: [1, 2, 3, 4, 5])),
            new OA\Parameter(name: 'sort', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['latest', 'oldest', 'highest', 'lowest'])),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', example: 25)),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', example: 1)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Paginated review list',
                content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/ReviewResource')),
            ),
            new OA\Response(response: 404, description: 'App not found'),
        ],
    )]
    public function index(ReviewIndexRequest $request, string $platform, string $externalId): AnonymousResourceCollection
    {
        $app = $this->resolveApp($platform, $externalId);

        $query = $app->reviews()
            ->when($request->validated('country_code'), fn ($q, $v) => $q->where('country_code', $v))
            ->when($request->validated('rating'), fn ($q, $v) => $q->where('rating', $v));

        $sort = $request->validated('sort', 'latest');
        $query = match ($sort) {
            'oldest' => $query->orderBy('review_date'),
            'highest' => $query->orderByDesc('rating')->orderByDesc('review_date'),
            'lowest' => $query->orderBy('rating')->orderByDesc('review_date'),
            default => $query->orderByDesc('review_date'),
        };

        $perPage = $request->validated('per_page', 25);

        return ReviewResource::collection($query->paginate($perPage));
    }

    #[OA\Get(
        path: '/apps/{platform}/{externalId}/reviews/summary',
        summary: 'Get review summary and rating distribution for an app',
        tags: ['Apps'],
        operationId: 'appReviewsSummary',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'platform', in: 'path', required: true, schema: new OA\Schema(type: 'string', enum: ['ios', 'android'])),
            new OA\Parameter(name: 'externalId', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'country_code', in: 'query', required: false, schema: new OA\Schema(type: 'string', example: 'US')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Review summary with rating distribution',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'total_reviews', type: 'integer', example: 150),
                        new OA\Property(property: 'average_rating', type: 'number', format: 'float', example: 4.2),
                        new OA\Property(
                            property: 'distribution',
                            type: 'object',
                            properties: [
                                new OA\Property(property: '1', type: 'integer', example: 5),
                                new OA\Property(property: '2', type: 'integer', example: 10),
                                new OA\Property(property: '3', type: 'integer', example: 20),
                                new OA\Property(property: '4', type: 'integer', example: 45),
                                new OA\Property(property: '5', type: 'integer', example: 70),
                            ],
                        ),
                        new OA\Property(
                            property: 'countries',
                            type: 'array',
                            items: new OA\Items(type: 'string'),
                            example: ['US', 'TR', 'DE'],
                        ),
                    ],
                ),
            ),
            new OA\Response(response: 404, description: 'App not found'),
        ],
    )]
    public function summary(string $platform, string $externalId): ReviewSummaryResource
    {
        $app = $this->resolveApp($platform, $externalId);

        $reviews = $app->reviews()
            ->when(request('country_code'), fn ($q, $v) => $q->where('country_code', $v));

        $total = $reviews->count();
        $average = $total > 0 ? round((float) $reviews->avg('rating'), 2) : 0;

        $distribution = $app->reviews()
            ->when(request('country_code'), fn ($q, $v) => $q->where('country_code', $v))
            ->selectRaw('rating, count(*) as count')
            ->groupBy('rating')
            ->pluck('count', 'rating');

        $dist = (object) [];
        for ($i = 1; $i <= 5; $i++) {
            $dist->{$i} = $distribution[$i] ?? 0;
        }

        $countries = $app->reviews()
            ->distinct()
            ->pluck('country_code')
            ->filter()
            ->values();

        return ReviewSummaryResource::make([
            'total_reviews' => $total,
            'average_rating' => $average,
            'distribution' => $dist,
            'countries' => $countries,
        ]);
    }
}
