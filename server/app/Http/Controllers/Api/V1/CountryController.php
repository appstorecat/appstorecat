<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseController;
use App\Models\Country;
use Illuminate\Http\JsonResponse;
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
                content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/Country')),
            ),
        ],
    )]
    public function __invoke(): JsonResponse
    {
        $countries = Country::where('is_active_ios', true)
            ->orWhere('is_active_android', true)
            ->orderBy('name')
            ->get(['code', 'name', 'emoji', 'ios_languages', 'android_languages']);

        return response()->json($countries);
    }
}
