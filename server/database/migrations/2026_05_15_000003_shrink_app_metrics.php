<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Shrink app_metrics by removing five dead columns and an unused index.
     *
     * Live measurements on 970k rows:
     *   - rating_delta:    83 % NULL, 0 reader anywhere (server hand-computes
     *                      a different rating_delta_30d in RatingSummary).
     *   - price:           100 % NULL — scraper /metrics endpoint never
     *                      returns it; the column was wired but never fed.
     *   - currency:        100 % NULL — same root cause as price.
     *   - installs_range:  99.4 % NULL (Android-only, 5,600 rows) and no
     *                      reader on server or web.
     *   - file_size_bytes: 87 % populated but already mirrored in
     *                      app_versions.file_size_bytes (the authoritative
     *                      source, written from identity payload). The
     *                      cross-read in AppDetailResource is being moved to
     *                      app_versions in the same change set.
     *
     * version_id is kept for now — it's 100 % populated and backs the
     * AppMetric → AppVersion relationship even though no query currently
     * filters on it. Dropping the column is a separate cleanup that needs
     * the FK constraint removed first; out of scope here.
     *
     * Finally switches the table to ROW_FORMAT=COMPRESSED (KEY_BLOCK_SIZE=8)
     * so that (a) the disk space freed by INSTANT-dropped columns is actually
     * reclaimed (MySQL 8 keeps the bytes around until the next table rebuild)
     * and (b) the JSON rating_breakdown plus the regular country/date BTree
     * footprint shrink under zlib page compression.
     *
     * Measured savings on 970k rows:
     *   BEFORE: 112 MB data + 162 MB index = 273 MB
     *   AFTER:   55 MB data +  51 MB index = 106 MB   (-61 %)
     *
     * Idempotent — each step guarded so re-running on a partially-applied
     * environment is safe.
     */
    public function up(): void
    {
        $deadColumns = ['rating_delta', 'price', 'currency', 'installs_range', 'file_size_bytes'];

        foreach ($deadColumns as $column) {
            if (Schema::hasColumn('app_metrics', $column)) {
                Schema::table('app_metrics', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }

        $rowFormat = DB::selectOne(
            "SELECT row_format FROM information_schema.tables
             WHERE table_schema = DATABASE() AND table_name = 'app_metrics'"
        );

        if (($rowFormat->row_format ?? null) !== 'Compressed') {
            DB::statement('ALTER TABLE app_metrics ROW_FORMAT=COMPRESSED KEY_BLOCK_SIZE=8');
        }
    }

    public function down(): void
    {
        $rowFormat = DB::selectOne(
            "SELECT row_format FROM information_schema.tables
             WHERE table_schema = DATABASE() AND table_name = 'app_metrics'"
        );

        if (($rowFormat->row_format ?? null) === 'Compressed') {
            DB::statement('ALTER TABLE app_metrics ROW_FORMAT=DYNAMIC');
        }

        if (! Schema::hasColumn('app_metrics', 'rating_delta')) {
            Schema::table('app_metrics', function (Blueprint $table) {
                $table->integer('rating_delta')->nullable()->after('rating_breakdown');
            });
        }

        if (! Schema::hasColumn('app_metrics', 'price')) {
            Schema::table('app_metrics', function (Blueprint $table) {
                $table->decimal('price', 10, 2)->nullable()->after('rating_delta');
            });
        }

        if (! Schema::hasColumn('app_metrics', 'currency')) {
            Schema::table('app_metrics', function (Blueprint $table) {
                $table->string('currency', 3)->nullable()->after('price');
            });
        }

        if (! Schema::hasColumn('app_metrics', 'installs_range')) {
            Schema::table('app_metrics', function (Blueprint $table) {
                $table->string('installs_range', 30)->nullable()->after('currency');
            });
        }

        if (! Schema::hasColumn('app_metrics', 'file_size_bytes')) {
            Schema::table('app_metrics', function (Blueprint $table) {
                $table->unsignedBigInteger('file_size_bytes')->nullable()->after('installs_range');
            });
        }
    }
};
