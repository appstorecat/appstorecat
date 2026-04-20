<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'app_id',
    'status', 'current_step',
    'progress_done', 'progress_total',
    'failed_items', 'error_message',
    'job_id',
    'started_at', 'completed_at', 'next_retry_at',
])]
class SyncStatus extends Model
{
    protected $table = 'sync_statuses';

    public const STATUS_QUEUED = 'queued';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const STEP_IDENTITY = 'identity';

    public const STEP_LISTINGS = 'listings';

    public const STEP_METRICS = 'metrics';

    public const STEP_FINALIZE = 'finalize';

    public const STEP_RECONCILING = 'reconciling';

    public const REASON_HTTP_500 = 'http_500';

    public const REASON_HTTP_429 = 'http_429';

    public const REASON_TIMEOUT = 'timeout';

    public const REASON_EMPTY_RESPONSE = 'empty_response';

    public const REASON_NETWORK_ERROR = 'network_error';

    /**
     * @return BelongsTo<App, $this>
     */
    public function app(): BelongsTo
    {
        return $this->belongsTo(App::class);
    }

    public function isActive(): bool
    {
        return in_array($this->status, [self::STATUS_QUEUED, self::STATUS_PROCESSING], true);
    }

    public function hasFailedItems(): bool
    {
        return ! empty($this->failed_items);
    }

    public function pushFailedItem(array $item): void
    {
        $items = $this->failed_items ?? [];
        $items[] = $item;
        $this->failed_items = $items;
    }

    public function removeFailedItem(callable $matcher): void
    {
        $items = collect($this->failed_items ?? [])
            ->reject($matcher)
            ->values()
            ->all();
        $this->failed_items = $items;
    }

    protected function casts(): array
    {
        return [
            'failed_items' => 'array',
            'progress_done' => 'integer',
            'progress_total' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'next_retry_at' => 'datetime',
        ];
    }
}
