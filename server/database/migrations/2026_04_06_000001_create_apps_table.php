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
            $table->unsignedTinyInteger('platform')
                ->comment('Store platform: 1=iOS, 2=Android. See App\\Enums\\Platform. Unique with external_id.');
            $table->string('external_id')
                ->comment('Store-provided app identifier (iTunes trackId / Play packageName).');
            $table->foreignId('publisher_id')->nullable()
                ->comment('FK -> publishers.id; null when publisher metadata not yet scraped. Null on publisher delete.')
                ->constrained('publishers')->nullOnDelete();
            $table->foreignId('category_id')->nullable()
                ->comment('FK -> store_categories.id (primary genre); null until identity sync resolves it.')
                ->constrained('store_categories')->nullOnDelete();
            $table->string('display_name')->nullable()
                ->comment('Canonical app name shown in UI; derived from the default-locale listing title.');
            $table->text('icon_url')->nullable()
                ->comment('Absolute URL to the latest app icon asset from the store CDN.');
            $table->char('origin_country_code', 2)->default('us')
                ->comment('FK -> countries.code; storefront the app was first discovered in. Drives default country for syncs.');
            $table->json('supported_locales')->nullable()
                ->comment('Array of BCP-47 locales the app publishes listings for (e.g. ["en","tr"]); null if unknown.');
            $table->date('original_release_date')->nullable()
                ->comment('Store-reported initial release date of the app (not the latest version).');
            $table->boolean('is_free')->default(true)
                ->comment('True if the app is listed free in its origin country; latest known value.');
            $table->tinyInteger('discovered_from')->nullable()
                ->comment('How the app entered our DB: see App\\Enums\\DiscoverSource (search, trending, import...).');
            $table->timestamp('discovered_at')->nullable()
                ->comment('When we first inserted this app row; separate from created_at for clarity.');
            $table->timestamp('last_synced_at')->nullable()
                ->comment('Last time the sync pipeline finalized successfully; drives the staleness query.');
            $table->boolean('is_available')->default(true)
                ->comment('False when the store returned "not found" / app was taken down; excludes from default listings.');
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
