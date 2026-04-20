<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\App;

use App\Http\Resources\Api\BaseResource;
use App\Models\StoreListing;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/** @mixin StoreListing */
#[OA\Schema(
    schema: 'ListingResource',
    allOf: [new OA\Schema(ref: '#/components/schemas/StoreListing')],
)]
class ListingResource extends BaseResource
{
    protected function getResourceData(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'version_id' => $this->resource->version_id,
            'locale' => $this->resource->locale,
            'title' => $this->resource->title,
            'subtitle' => $this->resource->subtitle,
            'description' => $this->resource->description,
            'promotional_text' => $this->resource->promotional_text,
            'whats_new' => $this->resource->whats_new,
            'icon_url' => $this->resource->icon_url,
            'screenshots' => $this->resource->screenshotUrls(),
            'video_url' => $this->resource->video_url,
            'price' => (float) $this->resource->price,
            'currency' => $this->resource->currency,
            'description_length' => $this->resource->description_length,
            'fetched_at' => $this->formatTimestamp($this->resource->fetched_at),
        ];
    }

    protected function getDefaultMessage(): string
    {
        return 'Listing retrieved successfully';
    }
}
