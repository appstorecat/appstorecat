<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('apps', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('platform');
            $table->string('external_id');
            $table->foreignId('publisher_id')->nullable()->constrained('publishers')->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('store_categories')->nullOnDelete();
            $table->string('display_name')->nullable();
            $table->text('icon_url')->nullable();
            $table->char('origin_country_code', 2)->default('us');
            $table->json('supported_locales')->nullable();
            $table->date('original_release_date')->nullable();
            $table->boolean('is_free')->default(true);
            $table->tinyInteger('discovered_from')->nullable();
            $table->timestamp('discovered_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->boolean('is_available')->default(true);
            $table->timestamps();

            $table->unique(['platform', 'external_id']);
            $table->index('last_synced_at');
            $table->index('discovered_from');
            $table->index(['platform', 'is_available', 'last_synced_at']);

            $table->foreign('origin_country_code')
                ->references('code')->on('countries')
                ->cascadeOnUpdate()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('apps');
    }
};
