<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Shrink app_store_listings via two idempotent steps:
     *
     *   1. Drop the unused (checksum) index. The audit found no reader uses
     *      the checksum column for SQL-side lookups; AppSyncer::saveListing
     *      compares checksums in PHP only. Index footprint ~16 MB on 520k
     *      rows of 32-byte md5 strings.
     *
     *   2. Switch the table to ROW_FORMAT=COMPRESSED (KEY_BLOCK_SIZE=8).
     *      InnoDB zlib-compresses each 16 KB page to 8 KB on disk. TEXT
     *      columns (description, screenshots JSON, whats_new) dominate the
     *      table — ~67 % of payload is these heavy fields and CAS analysis
     *      showed 41 % duplication. Page-level zlib captures part of that.
     *      Expected savings: 30-50 % on a 2.8 GB table (~1 GB reclaimed).
     *
     * Timestamps are kept: ~2 % of rows show updated_at ≠ created_at,
     * indicating the columns are actively used (re-fetch tracking).
     *
     * The compression ALTER rebuilds the table (~3-10 minutes on the local
     * dataset of 520k rows with large TEXT payloads). Run outside the daily
     * sync windows.
     */
    public function up(): void
    {
        $checksumIndexExists = DB::selectOne(
            "SELECT 1 AS ok FROM information_schema.statistics
             WHERE table_schema = DATABASE()
               AND table_name = 'app_store_listings'
               AND index_name = 'app_store_listings_checksum_index'
             LIMIT 1"
        );

        if ($checksumIndexExists) {
            Schema::table('app_store_listings', function (Blueprint $table) {
                $table->dropIndex('app_store_listings_checksum_index');
            });
        }

        $rowFormat = DB::selectOne(
            "SELECT row_format FROM information_schema.tables
             WHERE table_schema = DATABASE() AND table_name = 'app_store_listings'"
        );

        if (($rowFormat->row_format ?? null) !== 'Compressed') {
            DB::statement('ALTER TABLE app_store_listings ROW_FORMAT=COMPRESSED KEY_BLOCK_SIZE=8');
        }
    }

    public function down(): void
    {
        $rowFormat = DB::selectOne(
            "SELECT row_format FROM information_schema.tables
             WHERE table_schema = DATABASE() AND table_name = 'app_store_listings'"
        );

        if (($rowFormat->row_format ?? null) === 'Compressed') {
            DB::statement('ALTER TABLE app_store_listings ROW_FORMAT=DYNAMIC');
        }

        $indexExists = DB::selectOne(
            "SELECT 1 AS ok FROM information_schema.statistics
             WHERE table_schema = DATABASE()
               AND table_name = 'app_store_listings'
               AND index_name = 'app_store_listings_checksum_index'
             LIMIT 1"
        );

        if (! $indexExists) {
            Schema::table('app_store_listings', function (Blueprint $table) {
                $table->index('checksum', 'app_store_listings_checksum_index');
            });
        }
    }
};
