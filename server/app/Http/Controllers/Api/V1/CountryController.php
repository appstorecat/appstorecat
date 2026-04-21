<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseController;
use App\Http\Resources\Api\Country\CountryResource;
use App\Models\AppMetric;
use App\Models\Country;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Attributes as OA;

class CountryController extends BaseController
{
    #[OA\Get(
        path: '/countries',
        summary: 'List active countries',
        tags: ['Countries'],
        operationId: 'listCountries',
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'List of active countries',
                content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/CountryResource')),
            ),
        ],
    )]
    public function __invoke(): AnonymousResourceCollection
    {
        $countries = Country::query()
            ->where('code', '!=', AppMetric::GLOBAL_COUNTRY)
            ->where(fn ($q) => $q->where('is_active_ios', true)->orWhere('is_active_android', true))
            ->orderBy('name')
            ->get(['code', 'name', 'emoji', 'ios_languages', 'android_languages']);

        return CountryResource::collection($countries);
    }
}
