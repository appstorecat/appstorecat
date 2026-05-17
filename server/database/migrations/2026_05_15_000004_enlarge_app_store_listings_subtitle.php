<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Enlarge app_store_listings.subtitle from VARCHAR(255) to TEXT.
     *
     * The Google Play "short description" surface — which the Android
     * scraper maps onto our subtitle column — has no documented hard
     * cap on the scraper response and routinely exceeds 255 characters
     * for non-English locales (Polish, German, Spanish word-rich
     * translations of the same string).
     *
     * Live data showed 4,284 failed_items in sync_statuses, ALL of them
     * caused by the same insert exception:
     *
     *   SQLSTATE[22001]: String data, right truncated:
     *   1406 Data too long for column 'subtitle' at row 1
     *
     * Truncation in PHP is the wrong fix — it silently discards data
     * that the store actually exposes to users. Widening the column to
     * TEXT preserves the value end-to-end.
     *
     * On the local 520k-row, ROW_FORMAT=COMPRESSED table this MODIFY
     * is a full table rebuild and takes a few minutes. Schedule outside
     * the daily sync window.
     */
    public function up(): void
    {
        $columnType = DB::selectOne(
            "SELECT data_type FROM information_schema.columns
             WHERE table_schema = DATABASE()
               AND table_name = 'app_store_listings'
               AND column_name = 'subtitle'"
        );

        if (($columnType->data_type ?? null) !== 'text') {
            Schema::table('app_store_listings', function (Blueprint $table) {
                $table->text('subtitle')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        Schema::table('app_store_listings', function (Blueprint $table) {
            $table->string('subtitle')->nullable()->change();
        });
    }
};
