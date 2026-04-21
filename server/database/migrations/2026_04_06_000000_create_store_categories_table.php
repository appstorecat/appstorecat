<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_categories', function (Blueprint $table) {
            $table->id();
            $table->string('external_id')->nullable()
                ->comment('Store-provided genre id (iTunes genreId / Play category slug); null for custom buckets.');
            $table->string('name')
                ->comment('Human-readable category name in English (e.g. "Photo & Video").');
            $table->string('slug')
                ->comment('URL-safe identifier used in routes and API filters. Unique with (platform, type).');
            $table->unsignedTinyInteger('platform')
                ->comment('Store platform: 1=iOS, 2=Android. See App\\Enums\\Platform.');
            $table->string('type')->default('app')
                ->comment('Category kind: app, game, or chart-collection group; scopes the uniqueness key.');
            $table->foreignId('parent_id')->nullable()
                ->comment('FK -> store_categories.id for subcategories; null for top-level. Null on parent delete.')
                ->constrained('store_categories')->nullOnDelete();
            $table->smallInteger('priority')->default(0)
                ->comment('Manual ordering weight for UI listings; higher = shown first.');
            $table->timestamps();

            $table->unique(['platform', 'slug', 'type']);
            $table->index(['platform', 'type']);
            $table->index(['platform', 'external_id']);
            $table->index(['platform', 'parent_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_categories');
    }
};
