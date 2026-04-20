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
            $table->unsignedTinyInteger('platform');
            $table->string('collection', 30);
            $table->foreignId('category_id')->constrained('store_categories')->cascadeOnDelete();
            $table->char('country_code', 2)->default('us');
            $table->foreign('country_code')->references('code')->on('countries');
            $table->date('snapshot_date');
            $table->timestamps();

            $table->unique(['platform', 'collection', 'country_code', 'category_id', 'snapshot_date'], 'uniq_snapshot');
            $table->index(['platform', 'collection', 'country_code', 'snapshot_date'], 'idx_lookup');
        });

        Schema::create('trending_chart_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trending_chart_id')->constrained('trending_charts')->cascadeOnDelete();
            $table->unsignedSmallInteger('rank');
            $table->foreignId('app_id')->constrained('apps')->cascadeOnDelete();
            $table->decimal('price', 10, 2)->default(0);
            $table->string('currency', 3)->nullable();
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
