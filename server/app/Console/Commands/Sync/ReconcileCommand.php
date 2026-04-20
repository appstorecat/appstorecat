<?php

declare(strict_types=1);

namespace App\Console\Commands\Sync;

use App\Jobs\Sync\ReconcileFailedItemsJob;
use App\Models\SyncStatus;
use Illuminate\Console\Command;

class ReconcileCommand extends Command
{
    protected $signature = 'appstorecat:sync:reconcile';

    protected $description = 'Dispatch reconciliation jobs for sync_statuses with due failed_items';

    public function handle(): int
    {
        $batchSize = (int) config('appstorecat.sync.reconcile.batch_size', 50);
        $now = now();

        $statuses = SyncStatus::query()
            ->whereNotNull('failed_items')
            ->where(function ($q) {
                $q->whereJsonLength('failed_items', '>', 0);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('next_retry_at')
                    ->orWhere('next_retry_at', '<=', $now);
            })
            ->where('status', '!=', SyncStatus::STATUS_PROCESSING)
            ->limit($batchSize)
            ->get();

        if ($statuses->isEmpty()) {
            $this->components->info('No sync_statuses ready for reconciliation.');

            return self::SUCCESS;
        }

        foreach ($statuses as $status) {
            ReconcileFailedItemsJob::dispatch($status->id)->onQueue('sync-reconcile');
        }

        $this->components->info("Dispatched {$statuses->count()} reconciliation jobs.");

        return self::SUCCESS;
    }
}
