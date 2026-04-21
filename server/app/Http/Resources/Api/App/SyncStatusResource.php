<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\App;

use App\Http\Resources\Api\BaseResource;
use App\Models\SyncStatus;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/** @mixin SyncStatus */
#[OA\Schema(
    schema: 'SyncStatusResource',
    properties: [
        new OA\Property(property: 'app_id', type: 'integer'),
        new OA\Property(property: 'status', type: 'string', enum: ['queued', 'processing', 'completed', 'failed']),
        new OA\Property(property: 'current_step', type: 'string', enum: ['identity', 'listings', 'metrics', 'finalize', 'reconciling'], nullable: true),
        new OA\Property(property: 'progress', type: 'object', properties: [
            new OA\Property(property: 'done', type: 'integer'),
            new OA\Property(property: 'total', type: 'integer'),
        ]),
        new OA\Property(
            property: 'failed_items',
            type: 'array',
            items: new OA\Items(
                type: 'object',
                properties: [
                    new OA\Property(property: 'type', type: 'string', enum: ['listing', 'metric']),
                    new OA\Property(property: 'locale', type: 'string', nullable: true),
                    new OA\Property(property: 'country_code', type: 'string', nullable: true),
                    new OA\Property(property: 'reason', type: 'string', nullable: true),
                    new OA\Property(property: 'retry_count', type: 'integer'),
                    new OA\Property(property: 'last_attempted_at', type: 'string', format: 'date-time', nullable: true),
                    new OA\Property(property: 'next_retry_at', type: 'string', format: 'date-time', nullable: true),
                    new OA\Property(property: 'permanent_failure', type: 'boolean'),
                    new OA\Property(property: 'last_error', type: 'string', nullable: true),
                ],
            ),
        ),
        new OA\Property(property: 'failed_items_count', type: 'integer'),
        new OA\Property(property: 'error_message', type: 'string', nullable: true),
        new OA\Property(property: 'job_id', type: 'string', nullable: true),
        new OA\Property(property: 'started_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'completed_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'next_retry_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'elapsed_ms', type: 'integer', nullable: true),
    ],
)]
class SyncStatusResource extends BaseResource
{
    protected function getResourceData(Request $request): array
    {
        $s = $this->resource;
        $elapsedMs = null;
        if ($s->started_at) {
            $end = $s->completed_at ?? now();
            $elapsedMs = $end->diffInMilliseconds($s->started_at);
        }

        $failedItems = $s->failed_items ?? [];

        return [
            'app_id' => $s->app_id,
            'status' => $s->status,
            'current_step' => $s->current_step,
            'progress' => [
                'done' => $s->progress_done,
                'total' => $s->progress_total,
            ],
            'failed_items' => $failedItems,
            'failed_items_count' => count($failedItems),
            'error_message' => $s->error_message,
            'job_id' => $s->job_id,
            'started_at' => $this->formatTimestamp($s->started_at),
            'completed_at' => $this->formatTimestamp($s->completed_at),
            'next_retry_at' => $this->formatTimestamp($s->next_retry_at),
            'elapsed_ms' => $elapsedMs,
        ];
    }

    protected function getDefaultMessage(): string
    {
        return 'Sync status retrieved';
    }
}
