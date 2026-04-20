<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_store_listings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('app_id')->constrained('apps')->cascadeOnDelete();
            $table->foreignId('version_id')->nullable()->constrained('app_versions')->nullOnDelete();
            $table->string('language', 10);
            $table->string('title');
            $table->string('subtitle')->nullable();
            $table->text('description');
            $table->text('whats_new')->nullable();
            $table->json('screenshots')->nullable();
            $table->text('icon_url')->nullable();
            $table->text('video_url')->nullable();
            $table->decimal('price', 10, 2)->default(0);
            $table->string('currency', 3)->nullable();
            $table->timestamp('fetched_at');
            $table->string('checksum');
            $table->timestamps();

            $table->unique(['app_id', 'version_id', 'language'], 'app_store_listings_app_version_lang_unique');
            $table->index('version_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_store_listings');
    }
};
