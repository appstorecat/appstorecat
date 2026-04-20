<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseController;
use App\Http\Resources\Api\DashboardResource;
use App\Models\AppVersion;
use App\Models\StoreListingChange;
use OpenApi\Attributes as OA;

class DashboardController extends BaseController
{
    #[OA\Get(
        path: '/dashboard',
        summary: 'Get dashboard summary for current user',
        tags: ['Dashboard'],
        operationId: 'dashboard',
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Dashboard summary',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'total_apps', type: 'integer', example: 5),
                        new OA\Property(property: 'total_versions', type: 'integer', example: 12),
                        new OA\Property(property: 'total_changes', type: 'integer', example: 8),
                        new OA\Property(
                            property: 'recent_changes',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'id', type: 'integer'),
                                    new OA\Property(property: 'app_name', type: 'string'),
                                    new OA\Property(property: 'field_changed', type: 'string'),
                                    new OA\Property(property: 'language', type: 'string'),
                                    new OA\Property(property: 'detected_at', type: 'string', format: 'date-time'),
                                ],
                            ),
                        ),
                    ],
                ),
            ),
        ],
    )]
    public function __invoke(): DashboardResource
    {
        $user = request()->user();
        $appIds = $user->apps()->pluck('apps.id');

        $apps = $user->apps;

        $recentChanges = StoreListingChange::whereIn('app_id', $appIds)
            ->orderByDesc('detected_at')
            ->limit(5)
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'app_name' => $c->app->name,
                'field_changed' => $c->field_changed,
                'locale' => $c->language,
                'detected_at' => $c->detected_at->toIso8601String(),
            ]);

        return DashboardResource::make([
            'total_apps' => $apps->count(),
            'total_versions' => AppVersion::whereIn('app_id', $appIds)->count(),
            'total_changes' => StoreListingChange::whereIn('app_id', $appIds)->count(),
            'recent_changes' => $recentChanges,
        ]);
    }
}
