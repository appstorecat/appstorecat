<?php

declare(strict_types=1);

namespace App\Console\Commands\Sync;

use App\Models\SyncStatus;
use Illuminate\Console\Command;

class CleanupFailedItemsCommand extends Command
{
    protected $signature = 'appstorecat:sync:cleanup-failed-items
                            {--days=14 : Items older than N days are dropped}
                            {--dry-run : Report counts without writing}';

    protected $description = 'Trim stale failed_items off completed/failed sync_statuses rows';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $dryRun = (bool) $this->option('dry-run');
        $cutoff = now()->subDays($days);

        $query = SyncStatus::query()
            ->whereIn('status', [SyncStatus::STATUS_COMPLETED, SyncStatus::STATUS_FAILED])
            ->whereJsonLength('failed_items', '>', 0)
            ->where(function ($q) use ($cutoff) {
                $q->whereNull('completed_at')->orWhere('completed_at', '<', $cutoff);
            });

        $totalItems = 0;
        $touchedRows = 0;

        $query->each(function (SyncStatus $status) use ($dryRun, &$totalItems, &$touchedRows) {
            $items = $status->failed_items ?? [];
            $totalItems += \count($items);
            $touchedRows++;

            if (! $dryRun) {
                $status->forceFill([
                    'failed_items' => null,
                    'next_retry_at' => null,
                ])->save();
            }
        });

        if ($touchedRows === 0) {
            $this->components->info('No stale failed_items to clean up.');

            return self::SUCCESS;
        }

        $verb = $dryRun ? 'would clear' : 'cleared';
        $this->components->info(\sprintf(
            '%s %d failed_items across %d sync_statuses rows older than %d days.',
            ucfirst($verb),
            $totalItems,
            $touchedRows,
            $days,
        ));

        return self::SUCCESS;
    }
}
