<?php

declare(strict_types=1);

namespace App\Http\Resources\Api;

use App\Models\StoreListingChange;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/** @mixin StoreListingChange */
#[OA\Schema(
    schema: 'ChangeResource',
    required: ['id', 'field_changed', 'detected_at'],
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'app', properties: [
            new OA\Property(property: 'id', type: 'integer'),
            new OA\Property(property: 'name', type: 'string'),
            new OA\Property(property: 'platform', type: 'string'),
            new OA\Property(property: 'external_id', type: 'string'),
            new OA\Property(property: 'icon_url', type: 'string', nullable: true),
        ], type: 'object'),
        new OA\Property(property: 'version_id', type: 'integer', nullable: true),
        new OA\Property(property: 'language', type: 'string', example: 'us'),
        new OA\Property(property: 'field_changed', type: 'string', example: 'description'),
        new OA\Property(property: 'old_value', type: 'string', nullable: true),
        new OA\Property(property: 'new_value', type: 'string', nullable: true),
        new OA\Property(property: 'detected_at', type: 'string', format: 'date-time'),
    ],
)]
class ChangeResource extends BaseResource
{
    protected function getResourceData(Request $request): array
    {
        $app = $this->resource->app;

        return [
            'id' => $this->resource->id,
            'app' => $app ? [
                'id' => $app->id,
                'name' => $app->displayName(),
                'platform' => $app->platform,
                'external_id' => $app->external_id,
                'icon_url' => $app->displayIcon(),
            ] : null,
            'version' => $this->resource->version_id
                ? $this->resource->app?->versions()->where('id', $this->resource->version_id)->value('version')
                : null,
            'previous_version' => $this->resource->version_id
                ? $this->resource->app?->versions()->where('id', '<', $this->resource->version_id)->orderByDesc('id')->value('version')
                : null,
            'language' => $this->resource->language,
            'field_changed' => $this->resource->field_changed,
            'old_value' => $this->resource->field_changed === 'screenshots' ? null : $this->resource->old_value,
            'new_value' => $this->resource->field_changed === 'screenshots' ? null : $this->resource->new_value,
            'detected_at' => $this->formatTimestamp($this->resource->detected_at),
        ];
    }

    protected function getDefaultMessage(): string
    {
        return 'Changes retrieved successfully';
    }
}
