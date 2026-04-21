<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('app_id')
                ->comment('FK -> apps.id. Cascade on app delete. Unique with (country_code, date) — one row per app/country/day.')
                ->constrained('apps')->cascadeOnDelete();
            $table->foreignId('version_id')->nullable()
                ->comment('FK -> app_versions.id; the live version on this date. Null on version delete.')
                ->constrained('app_versions')->nullOnDelete();
            $table->char('country_code', 2)
                ->comment('FK -> countries.code. Storefront this metric snapshot belongs to.');
            $table->date('date')
                ->comment('Calendar date (UTC) of this snapshot. One snapshot per app/country/day.');

            // Rating
            $table->decimal('rating', 3, 2)->default(0)
                ->comment('Average store rating at snapshot time, 0.00–5.00.');
            $table->unsignedInteger('rating_count')->default(0)
                ->comment('Total number of ratings reported by the store for this country on this date.');
            $table->json('rating_breakdown')->nullable()
                ->comment('Per-star counts: {"1": n, "2": n, ..., "5": n}. Null when scraper did not return it.');
            $table->integer('rating_delta')->nullable()
                ->comment('Change in rating_count vs. previous snapshot; negative allowed. Null if no prior row.');

            // Pricing (country-specific on iOS)
            $table->decimal('price', 10, 2)->nullable()
                ->comment('Listed price in the country storefront currency; null = unknown, 0 = free.');
            $table->string('currency', 3)->nullable()
                ->comment('ISO-4217 currency code matching price; null when price is null.');

            // Store metadata
            $table->string('installs_range', 30)->nullable()
                ->comment('Google Play installs bucket (e.g. "1,000,000+"). Null on iOS (not exposed).');
            $table->unsignedBigInteger('file_size_bytes')->nullable()
                ->comment('App binary size at snapshot time in bytes; null when not reported.');
            $table->boolean('is_available')->default(true)
                ->comment('False when store reported the app unavailable in this country on this date.');

            $table->timestamps();

            $table->unique(['app_id', 'country_code', 'date'], 'app_metrics_app_country_date_unique');
            $table->index(['app_id', 'date']);
            $table->index(['country_code', 'date']);

            $table->foreign('country_code')
                ->references('code')->on('countries')
                ->cascadeOnUpdate()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_metrics');
    }
};
