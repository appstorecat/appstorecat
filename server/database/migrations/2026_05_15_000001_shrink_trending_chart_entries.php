<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Shrink trending_chart_entries via three idempotent steps:
     *
     *   1. Drop the redundant standalone (app_id) index — the
     *      (app_id, trending_chart_id) composite already covers app-only
     *      lookups via leftmost-prefix rule.
     *
     *   2. Drop the unused created_at / updated_at columns — measured on a
     *      43M-row table, 0 rows had updated_at ≠ created_at, and no code
     *      reads either column. Snapshot time is already available via the
     *      parent trending_charts.snapshot_date.
     *
     *   3. Switch the table to ROW_FORMAT=COMPRESSED (KEY_BLOCK_SIZE=8).
     *      InnoDB zlib-compresses each 16 KB page to 8 KB on disk.
     *      Measured savings on a 43M-row table:
     *        BEFORE: 2813 MB data + 4876 MB index = 7689 MB
     *        AFTER:  1311 MB data + 1278 MB index = 2588 MB  (-66 %)
     *
     * Each step is guarded so the migration is safe to re-run on environments
     * where some steps were applied manually (e.g. local during prototyping).
     * The compression ALTER rebuilds the table (~5-15 minutes on the local
     * dataset). Run outside the daily chart cron window (00:30 UTC + buffer).
     */
    public function up(): void
    {
        $appIdIndexExists = DB::selectOne(
            "SELECT 1 AS ok FROM information_schema.statistics
             WHERE table_schema = DATABASE()
               AND table_name = 'trending_chart_entries'
               AND index_name = 'trending_chart_entries_app_id_index'
             LIMIT 1"
        );

        if ($appIdIndexExists) {
            Schema::table('trending_chart_entries', function (Blueprint $table) {
                $table->dropIndex('trending_chart_entries_app_id_index');
            });
        }

        if (Schema::hasColumn('trending_chart_entries', 'created_at')) {
            Schema::table('trending_chart_entries', function (Blueprint $table) {
                $table->dropColumn('created_at');
            });
        }

        if (Schema::hasColumn('trending_chart_entries', 'updated_at')) {
            Schema::table('trending_chart_entries', function (Blueprint $table) {
                $table->dropColumn('updated_at');
            });
        }

        $rowFormat = DB::selectOne(
            "SELECT row_format FROM information_schema.tables
             WHERE table_schema = DATABASE() AND table_name = 'trending_chart_entries'"
        );

        if (($rowFormat->row_format ?? null) !== 'Compressed') {
            DB::statement('ALTER TABLE trending_chart_entries ROW_FORMAT=COMPRESSED KEY_BLOCK_SIZE=8');
        }
    }

    public function down(): void
    {
        $rowFormat = DB::selectOne(
            "SELECT row_format FROM information_schema.tables
             WHERE table_schema = DATABASE() AND table_name = 'trending_chart_entries'"
        );

        if (($rowFormat->row_format ?? null) === 'Compressed') {
            DB::statement('ALTER TABLE trending_chart_entries ROW_FORMAT=DYNAMIC');
        }

        if (! Schema::hasColumn('trending_chart_entries', 'created_at')) {
            Schema::table('trending_chart_entries', function (Blueprint $table) {
                $table->timestamps();
            });
        }

        $indexExists = DB::selectOne(
            "SELECT 1 AS ok FROM information_schema.statistics
             WHERE table_schema = DATABASE()
               AND table_name = 'trending_chart_entries'
               AND index_name = 'trending_chart_entries_app_id_index'
             LIMIT 1"
        );

        if (! $indexExists) {
            Schema::table('trending_chart_entries', function (Blueprint $table) {
                $table->index('app_id', 'trending_chart_entries_app_id_index');
            });
        }
    }
};
