<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('app_keyword_densities');
    }

    public function down(): void
    {
        Schema::create('app_keyword_densities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('app_id')->constrained('apps')->cascadeOnDelete();
            $table->foreignId('version_id')->nullable()->constrained('app_versions')->nullOnDelete();
            $table->string('language', 10);
            $table->tinyInteger('ngram_size');
            $table->string('keyword');
            $table->unsignedInteger('count');
            $table->decimal('density', 5, 2);
            $table->timestamps();

            $table->index(['app_id', 'version_id', 'language', 'ngram_size'], 'idx_keyword_lookup');
        });
    }
};
