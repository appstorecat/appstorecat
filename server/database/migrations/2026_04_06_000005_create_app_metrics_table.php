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
            $table->foreignId('app_id')->constrained('apps')->cascadeOnDelete();
            $table->foreignId('version_id')->nullable()->constrained('app_versions')->nullOnDelete();
            $table->char('country_code', 2);
            $table->date('date');

            // Rating
            $table->decimal('rating', 3, 2)->default(0);
            $table->unsignedInteger('rating_count')->default(0);
            $table->json('rating_breakdown')->nullable();
            $table->integer('rating_delta')->nullable();

            // Pricing (country-specific on iOS)
            $table->decimal('price', 10, 2)->nullable();
            $table->string('currency', 3)->nullable();

            // Store metadata
            $table->string('installs_range', 30)->nullable();
            $table->unsignedBigInteger('file_size_bytes')->nullable();
            $table->boolean('is_available')->default(true);

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
