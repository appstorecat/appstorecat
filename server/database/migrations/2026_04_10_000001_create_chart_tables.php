<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trending_charts', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('platform')
                ->comment('Store platform: 1=iOS, 2=Android. See App\\Enums\\Platform.');
            $table->string('collection', 30)
                ->comment('Chart collection slug: top_free, top_paid, top_grossing. See App\\Enums\\ChartCollection.');
            $table->foreignId('category_id')
                ->comment('FK -> store_categories.id. Category this chart scopes to (overall uses the "all" bucket).')
                ->constrained('store_categories')->cascadeOnDelete();
            $table->char('country_code', 2)->default('us')
                ->comment('FK -> countries.code. Storefront the chart was captured from.');
            $table->foreign('country_code')->references('code')->on('countries');
            $table->date('snapshot_date')
                ->comment('Calendar date (UTC) of this chart snapshot; one snapshot per chart per day.');
            $table->timestamps();

            $table->unique(['platform', 'collection', 'country_code', 'category_id', 'snapshot_date'], 'uniq_snapshot');
            $table->index(['platform', 'collection', 'country_code', 'snapshot_date'], 'idx_lookup');
        });

        Schema::create('trending_chart_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trending_chart_id')
                ->comment('FK -> trending_charts.id. Parent snapshot; cascade on snapshot delete.')
                ->constrained('trending_charts')->cascadeOnDelete();
            $table->unsignedSmallInteger('rank')
                ->comment('1-indexed position in the chart at snapshot time; lower = higher position.');
            $table->foreignId('app_id')
                ->comment('FK -> apps.id. Ranked app in this snapshot; cascade on app delete.')
                ->constrained('apps')->cascadeOnDelete();
            $table->decimal('price', 10, 2)->default(0)
                ->comment('Price displayed on the chart at snapshot time in the storefront currency; 0 = free.');
            $table->string('currency', 3)->nullable()
                ->comment('ISO-4217 currency code matching price; null when not reported.');
            $table->timestamps();

            $table->index(['trending_chart_id', 'rank']);
            $table->index(['app_id', 'trending_chart_id']);
            $table->index('app_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trending_chart_entries');
        Schema::dropIfExists('trending_charts');
    }
};
