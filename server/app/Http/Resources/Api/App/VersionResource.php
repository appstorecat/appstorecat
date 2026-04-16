<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\App;

use App\Http\Resources\Api\BaseResource;
use App\Models\AppVersion;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/** @mixin AppVersion */
#[OA\Schema(
    schema: 'VersionResource',
    allOf: [new OA\Schema(ref: '#/components/schemas/AppVersion')],
)]
class VersionResource extends BaseResource
{
    protected function getResourceData(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'version' => $this->resource->version,
            'release_date' => $this->resource->release_date?->toDateString(),
            'whats_new' => $this->resource->whats_new,
            'file_size_bytes' => $this->resource->file_size_bytes,
            'created_at' => $this->formatTimestamp($this->resource->created_at),
        ];
    }

    protected function getDefaultMessage(): string
    {
        return 'Version retrieved successfully';
    }
}
