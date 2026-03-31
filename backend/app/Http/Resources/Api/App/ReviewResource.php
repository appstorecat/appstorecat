<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\App;

use App\Http\Resources\Api\BaseResource;
use App\Models\Review;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/** @mixin Review */
#[OA\Schema(
    schema: 'ReviewResource',
    allOf: [new OA\Schema(ref: '#/components/schemas/Review')],
)]
class ReviewResource extends BaseResource
{
    protected function getResourceData(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'country_code' => $this->resource->country_code,
            'external_id' => $this->resource->external_id,
            'author' => $this->resource->author,
            'title' => $this->resource->title,
            'body' => $this->resource->body,
            'rating' => $this->resource->rating,
            'review_date' => $this->resource->review_date?->toDateString(),
            'app_version' => $this->resource->app_version,
        ];
    }

    protected function getDefaultMessage(): string
    {
        return 'Review retrieved successfully';
    }
}
