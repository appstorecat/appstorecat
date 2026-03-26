<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('app_id')->constrained('apps')->cascadeOnDelete();
            $table->string('country_code', 2)->nullable();
            $table->foreign('country_code')->references('code')->on('countries')->nullOnDelete();
            $table->string('external_id')->nullable();
            $table->string('author')->nullable();
            $table->string('title')->nullable();
            $table->text('body')->nullable();
            $table->tinyInteger('rating');
            $table->date('review_date')->nullable();
            $table->string('app_version')->nullable();
            $table->timestamps();

            $table->unique(['app_id', 'external_id']);
            $table->index(['app_id', 'review_date']);
            $table->index(['app_id', 'rating']);
            $table->index(['app_id', 'country_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_reviews');
    }
};
